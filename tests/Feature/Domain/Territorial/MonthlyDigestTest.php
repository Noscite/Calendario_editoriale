<?php

declare(strict_types=1);

use App\Domain\Post\Enums\PostType;
use App\Domain\Post\Models\Post;
use App\Domain\Territorial\Generators\EventPostGenerator;
use App\Domain\Territorial\Jobs\GenerateTerritorialPostsJob;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Services\TerritoryMatcher;

function makeTerritorialEvent(string $extId, string $title, string $startAt, string $endAt): TerritorialEvent
{
    return TerritorialEvent::create([
        'source'        => 'e015',
        'external_id'   => $extId,
        'title'         => $title,
        'description'   => 'Test',
        'start_at'      => $startAt,
        'end_at'        => $endAt,
        'city'          => 'Masate',
        'province'      => 'Milano',
        'status'        => 'active',
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ]);
}

function bindDigestMocks(TerritorialEvent ...$events): void
{
    $generator = Mockery::mock(EventPostGenerator::class);
    // Per gli eventi singoli (T-3/T+1) — accettiamo qualunque
    $generator->shouldReceive('generate')->andReturn([
        'title' => 'event title', 'content' => 'event content',
        'hashtags' => '#a #b', 'cta' => 'click',
    ]);
    $generator->shouldReceive('generateMonthlyDigest')->andReturn([
        'title'    => 'Eventi del mese',
        'content'  => 'Panoramica eventi del mese',
        'hashtags' => '#mese #eventi',
        'cta'      => 'Salva il post',
    ]);
    app()->instance(EventPostGenerator::class, $generator);

    $matcher = Mockery::mock(TerritoryMatcher::class);
    $matcher->shouldReceive('eligibleEvents')
        ->andReturn(new \Illuminate\Database\Eloquent\Collection($events));
    app()->instance(TerritoryMatcher::class, $matcher);
}

it('generates 1 monthly digest per first-of-month within project range', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, [
        'start_date' => '2026-05-01',
        'end_date'   => '2026-07-31',
        'status'     => 'generating',
    ]);

    $e1 = makeTerritorialEvent('a', 'Evento Maggio', '2026-05-15 10:00', '2026-05-15 18:00');
    $e2 = makeTerritorialEvent('b', 'Evento Giugno', '2026-06-10 10:00', '2026-06-10 18:00');
    $e3 = makeTerritorialEvent('c', 'Evento Luglio', '2026-07-20 10:00', '2026-07-20 18:00');

    bindDigestMocks($e1, $e2, $e3);

    (new GenerateTerritorialPostsJob($project->id))->handle(app(EventPostGenerator::class));

    $digestPosts = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', PostType::TerritorialMonthlyDigest->value)
        ->get();

    // 3 mesi (05, 06, 07) × 3 piattaforme default = 9
    expect($digestPosts->count())->toBe(9);

    $months = $digestPosts->pluck('scheduled_date')
        ->map(fn ($d) => $d->format('Y-m-d'))
        ->unique()->sort()->values()->all();
    expect($months)->toBe(['2026-05-01', '2026-06-01', '2026-07-01']);
});

it('skips months without events', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, [
        'start_date' => '2026-05-01',
        'end_date'   => '2026-07-31',
        'status'     => 'generating',
    ]);

    $e = makeTerritorialEvent('b', 'Solo Giugno', '2026-06-10 10:00', '2026-06-10 18:00');
    bindDigestMocks($e);

    (new GenerateTerritorialPostsJob($project->id))->handle(app(EventPostGenerator::class));

    $digestMonths = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', PostType::TerritorialMonthlyDigest->value)
        ->pluck('scheduled_date')
        ->map(fn ($d) => $d->format('Y-m-d'))
        ->unique()->values()->all();

    expect($digestMonths)->toBe(['2026-06-01']);
});

it('includes multi-month events in their overlapping monthly digests', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, [
        'start_date' => '2026-05-01',
        'end_date'   => '2026-07-31',
        'status'     => 'generating',
    ]);

    $multi = makeTerritorialEvent('multi', 'Maggio Masatese', '2026-05-02 10:00', '2026-07-06 23:00');
    bindDigestMocks($multi);

    (new GenerateTerritorialPostsJob($project->id))->handle(app(EventPostGenerator::class));

    $digests = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', PostType::TerritorialMonthlyDigest->value)
        ->where('platform', 'linkedin')
        ->get();

    expect($digests->count())->toBe(3);

    foreach ($digests as $digest) {
        $eventIds = $digest->generation_metadata['event_ids'] ?? [];
        expect($eventIds)->toContain($multi->id);
    }
});

it('does not duplicate digest on rerun (idempotency)', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, [
        'start_date' => '2026-05-01',
        'end_date'   => '2026-05-31',
        'status'     => 'generating',
    ]);

    $e = makeTerritorialEvent('x', 'X', '2026-05-15 10:00', '2026-05-15 18:00');
    bindDigestMocks($e);

    $generator = app(EventPostGenerator::class);

    (new GenerateTerritorialPostsJob($project->id))->handle($generator);
    $countAfterFirst = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', PostType::TerritorialMonthlyDigest->value)
        ->count();

    (new GenerateTerritorialPostsJob($project->id))->handle($generator);
    $countAfterSecond = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', PostType::TerritorialMonthlyDigest->value)
        ->count();

    expect($countAfterFirst)->toBe($countAfterSecond);
    expect($countAfterFirst)->toBeGreaterThan(0);
});
