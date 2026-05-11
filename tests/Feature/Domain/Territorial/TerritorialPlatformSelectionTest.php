<?php

declare(strict_types=1);

use App\Domain\Post\Enums\Platform;
use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Territorial\Generators\EventPostGenerator;
use App\Domain\Territorial\Jobs\GenerateTerritorialPostsJob;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Services\TerritoryMatcher;

/**
 * Contratto: $project->platforms è source of truth assoluta della
 * generazione. SocialConnection NON influenza la selezione delle
 * piattaforme — riflette solo lo stato di pubblicazione (i post per
 * platforms non connesse restano DRAFT). Edge case: project senza
 * platforms → fallback config default.
 *
 * I test usano gli helper fakeEventGenerator() / fakeMatcherReturning()
 * definiti in GenerateTerritorialPostsWithoutSocialTest.php (caricati
 * dallo stesso autoload Pest).
 */
beforeEach(function () {
    [, $org] = createAuthenticatedUser();
    $this->brand = createBrand($org, ['vertical' => 'unpli_regional']);

    $this->event = TerritorialEvent::create([
        'source'        => 'e015',
        'external_id'   => 'evt-platform-selection-' . \Illuminate\Support\Str::random(6),
        'title'         => 'Test event',
        'start_at'      => '2026-05-15 21:00:00',
        'end_at'        => '2026-05-15 23:00:00',
        'city'          => 'Milano',
        'province'      => 'Milano',
        'status'        => 'active',
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ]);

    fakeMatcherReturning($this->event);
});

it('generates posts ONLY for project-selected platforms regardless of social connections', function () {
    $project = createProject($this->brand, [
        'platforms'  => ['facebook', 'instagram'],
        'start_date' => '2026-05-10',
        'end_date'   => '2026-05-29',
        'status'     => 'generating',
    ]);

    expect(SocialConnection::where('brand_id', $this->brand->id)->count())->toBe(0);

    (new GenerateTerritorialPostsJob($project->id))->handle(
        fakeEventGenerator(),
        app(\App\Domain\Generation\Services\GenerationProgressService::class),
    );

    $platforms = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', 'territorial_event')
        ->pluck('platform')
        ->map(fn (Platform $p) => $p->value)
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($platforms)->toBe(['facebook', 'instagram']);
    expect($platforms)->not->toContain('linkedin');
});

it('generates posts for ALL project platforms even without social connections (draft mode)', function () {
    $project = createProject($this->brand, [
        'platforms'  => ['facebook', 'instagram', 'linkedin'],
        'start_date' => '2026-05-10',
        'end_date'   => '2026-05-29',
        'status'     => 'generating',
    ]);

    (new GenerateTerritorialPostsJob($project->id))->handle(
        fakeEventGenerator(),
        app(\App\Domain\Generation\Services\GenerationProgressService::class),
    );

    $platforms = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', 'territorial_event')
        ->pluck('platform')
        ->map(fn (Platform $p) => $p->value)
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($platforms)->toBe(['facebook', 'instagram', 'linkedin']);
});

it('falls back to config default if project has no platforms set (defensive edge case)', function () {
    $project = createProject($this->brand, [
        'platforms'  => [],
        'start_date' => '2026-05-10',
        'end_date'   => '2026-05-29',
        'status'     => 'generating',
    ]);

    (new GenerateTerritorialPostsJob($project->id))->handle(
        fakeEventGenerator(),
        app(\App\Domain\Generation\Services\GenerationProgressService::class),
    );

    $platforms = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', 'territorial_event')
        ->pluck('platform')
        ->map(fn (Platform $p) => $p->value)
        ->unique()
        ->sort()
        ->values()
        ->all();

    $expectedDefault = config('territorial.default_platforms', ['linkedin', 'instagram', 'facebook']);
    sort($expectedDefault);
    expect($platforms)->toBe($expectedDefault);
});

it('ignores SocialConnections completely (decision: only affects publishing, not generation)', function () {
    $project = createProject($this->brand, [
        'platforms'  => ['facebook'],
        'start_date' => '2026-05-10',
        'end_date'   => '2026-05-29',
        'status'     => 'generating',
    ]);

    // SocialConnection attive su LinkedIn e Instagram (NOT facebook): non devono influire.
    SocialConnection::create([
        'brand_id'            => $this->brand->id,
        'organization_id'     => $this->brand->organization_id,
        'platform'            => Platform::LinkedIn,
        'is_active'           => true,
        'access_token'        => 'fake-token-linkedin',
        'external_account_id' => 'urn:li:fake',
    ]);
    SocialConnection::create([
        'brand_id'            => $this->brand->id,
        'organization_id'     => $this->brand->organization_id,
        'platform'            => Platform::Instagram,
        'is_active'           => true,
        'access_token'        => 'fake-token-instagram',
        'external_account_id' => 'ig-fake',
    ]);

    (new GenerateTerritorialPostsJob($project->id))->handle(
        fakeEventGenerator(),
        app(\App\Domain\Generation\Services\GenerationProgressService::class),
    );

    $platforms = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', 'territorial_event')
        ->pluck('platform')
        ->map(fn (Platform $p) => $p->value)
        ->unique()
        ->values()
        ->all();

    expect($platforms)->toBe(['facebook']);
});
