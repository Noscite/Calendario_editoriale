<?php

declare(strict_types=1);

use App\Domain\Post\Enums\Platform;
use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Territorial\Generators\EventPostGenerator;
use App\Domain\Territorial\Jobs\GenerateTerritorialPostsJob;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Services\TerritoryMatcher;
use Illuminate\Support\Collection;

function fakeEventGenerator(): EventPostGenerator
{
    $fakeContent = [
        'title'    => 'Fake territorial title',
        'content'  => 'Fake content for test',
        'hashtags' => '#fake #test #event',
        'cta'      => 'Salva la data',
    ];
    $fakeUsage = new \App\Domain\AiUsage\Data\UsageRecord(
        provider: 'anthropic', model: 'claude-sonnet-4-6',
        inputTokens: 100, outputTokens: 50, costUsd: 0.001, costEur: 0.001,
    );
    $mock = Mockery::mock(EventPostGenerator::class);
    $mock->shouldReceive('generate')->andReturn($fakeContent);
    $mock->shouldReceive('generateWithUsage')->andReturn(['content' => $fakeContent, 'usage' => $fakeUsage]);
    $mock->shouldReceive('generateMonthlyDigest')->andReturn($fakeContent);
    $mock->shouldReceive('generateMonthlyDigestWithUsage')->andReturn(['content' => $fakeContent, 'usage' => $fakeUsage]);
    return $mock;
}

function fakeMatcherReturning(TerritorialEvent ...$events): void
{
    $matcher = Mockery::mock(TerritoryMatcher::class);
    $matcher->shouldReceive('eligibleEvents')
        ->andReturn(new \Illuminate\Database\Eloquent\Collection($events));
    app()->instance(TerritoryMatcher::class, $matcher);
}

it('generates territorial post drafts on default platforms when brand has no active social connections', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, [
        'start_date' => '2026-05-10',
        'end_date'   => '2026-05-29',
        'status'     => 'generating',
    ]);

    $event = TerritorialEvent::create([
        'source'        => 'e015',
        'external_id'   => 'evt-test-001',
        'title'         => 'Test Silent Disco',
        'description'   => 'Test',
        'start_at'      => '2026-05-15 21:00:00',
        'end_at'        => '2026-05-15 23:59:00',
        'city'          => 'Masate',
        'province'      => 'Milano',
        'status'        => 'active',
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ]);

    fakeMatcherReturning($event);

    expect(SocialConnection::where('brand_id', $brand->id)->where('is_active', true)->count())->toBe(0);

    (new GenerateTerritorialPostsJob($project->id))->handle(fakeEventGenerator(), app(\App\Domain\Generation\Services\GenerationProgressService::class));

    $generatedPosts = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', 'territorial_event')
        ->get();

    expect($generatedPosts->count())->toBeGreaterThan(0);

    $platformValues = $generatedPosts->pluck('platform')->map(fn (Platform $p) => $p->value)->unique()->sort()->values()->all();
    expect($platformValues)->toContain('linkedin');
    expect($platformValues)->toContain('instagram');
    expect($platformValues)->toContain('facebook');
});

it('uses active social connections when brand has any', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, [
        'start_date' => '2026-05-10',
        'end_date'   => '2026-05-29',
        'status'     => 'generating',
    ]);

    SocialConnection::create([
        'brand_id'            => $brand->id,
        'organization_id'     => $brand->organization_id,
        'platform'            => Platform::LinkedIn,
        'is_active'           => true,
        'access_token'        => 'fake-token',
        'external_account_id' => 'urn:li:fake',
    ]);

    $event = TerritorialEvent::create([
        'source'        => 'e015',
        'external_id'   => 'evt-test-002',
        'title'         => 'Test Event LinkedIn-only',
        'start_at'      => '2026-05-15 21:00:00',
        'end_at'        => '2026-05-15 23:00:00',
        'city'          => 'Milano',
        'province'      => 'Milano',
        'status'        => 'active',
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ]);

    fakeMatcherReturning($event);

    (new GenerateTerritorialPostsJob($project->id))->handle(fakeEventGenerator(), app(\App\Domain\Generation\Services\GenerationProgressService::class));

    $platforms = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', 'territorial_event')
        ->pluck('platform')->map(fn (Platform $p) => $p->value)->unique()->all();

    expect($platforms)->toBe(['linkedin']);
});
