<?php

declare(strict_types=1);

use App\Domain\Territorial\Jobs\GenerateTerritorialPostsJob;
use App\Domain\Territorial\Jobs\SyncTerritorialEventsJob;
use App\Domain\Territorial\Models\TerritorialEvent;
use Illuminate\Support\Facades\Bus;

it('POST /projects/{id}/territorial/generate dispatches job for eligible vertical', function () {
    Bus::fake([GenerateTerritorialPostsJob::class]);

    [$user, $org] = createAuthenticatedUser();
    $brand   = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand);

    $response = $this->actingAs($user)
        ->postJson("/api/projects/{$project->id}/territorial/generate");

    $response->assertOk()->assertJsonFragment(['status' => 'dispatched']);
    Bus::assertDispatched(GenerateTerritorialPostsJob::class);
});

it('POST /projects/{id}/territorial/generate returns 422 for non-eligible brand', function () {
    Bus::fake([GenerateTerritorialPostsJob::class]);

    [$user, $org] = createAuthenticatedUser();
    $brand   = createBrand($org, ['vertical' => null]); // vertical assente
    $project = createProject($brand);

    $response = $this->actingAs($user)
        ->postJson("/api/projects/{$project->id}/territorial/generate");

    $response->assertStatus(422)->assertJsonFragment(['status' => 'not_eligible']);
    Bus::assertNotDispatched(GenerateTerritorialPostsJob::class);
});

it('POST /territorial/sync dispatches sync job', function () {
    Bus::fake([SyncTerritorialEventsJob::class]);

    [$user] = createAuthenticatedUser();

    $response = $this->actingAs($user)->postJson('/api/territorial/sync');

    $response->assertOk()->assertJsonFragment(['status' => 'dispatched']);
    Bus::assertDispatched(SyncTerritorialEventsJob::class);
});

it('GET /territorial/events lists active events ordered by start_at', function () {
    [$user] = createAuthenticatedUser();

    TerritorialEvent::create([
        'source' => 'fake', 'external_id' => 'a',
        'title' => 'Future event', 'status' => 'active',
        'start_at' => now()->addDays(30),
        'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);
    TerritorialEvent::create([
        'source' => 'fake', 'external_id' => 'b',
        'title' => 'Past cancelled', 'status' => 'cancelled',
        'start_at' => now()->subDays(10),
        'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/territorial/events');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['title'])->toBe('Future event');
});

it('requires authentication', function () {
    $this->postJson('/api/territorial/sync')->assertStatus(401);
    $this->getJson('/api/territorial/events')->assertStatus(401);
});
