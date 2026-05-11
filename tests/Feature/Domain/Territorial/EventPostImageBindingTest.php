<?php

declare(strict_types=1);

use App\Domain\Post\Models\Post;
use App\Domain\Territorial\Generators\EventPostGenerator;
use App\Domain\Territorial\Jobs\GenerateTerritorialPostsJob;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Services\TerritoryMatcher;
use Illuminate\Support\Facades\Storage;

function bindEventBindingMocks(TerritorialEvent ...$events): void
{
    $generator = Mockery::mock(EventPostGenerator::class);
    $generator->shouldReceive('generate')->andReturn([
        'title'    => 'title',
        'content'  => 'content',
        'hashtags' => '#a #b',
        'cta'      => 'cta',
    ]);
    $generator->shouldReceive('generateMonthlyDigest')->andReturn([
        'title'    => 'digest',
        'content'  => 'digest content',
        'hashtags' => '#m',
        'cta'      => 'cta',
    ]);
    app()->instance(EventPostGenerator::class, $generator);

    $matcher = Mockery::mock(TerritoryMatcher::class);
    $matcher->shouldReceive('eligibleEvents')
        ->andReturn(new \Illuminate\Database\Eloquent\Collection($events));
    app()->instance(TerritoryMatcher::class, $matcher);
}

function makeEventForBinding(string $extId, ?string $imagePath): TerritorialEvent
{
    return TerritorialEvent::create([
        'source'        => 'e015',
        'external_id'   => $extId,
        'title'         => 'Evento ' . $extId,
        'description'   => 'desc',
        'start_at'      => '2026-05-15 10:00',
        'end_at'        => '2026-05-15 18:00',
        'city'          => 'Masate',
        'province'      => 'Milano',
        'status'        => 'active',
        'image_path'    => $imagePath,
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ]);
}

it('binds event image_path to post image_url as public storage URL', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, [
        'start_date' => '2026-05-10',
        'end_date'   => '2026-05-29',
        'status'     => 'generating',
    ]);

    $event = makeEventForBinding('evt-img', 'territorial/events/poster-123.jpg');

    bindEventBindingMocks($event);

    (new GenerateTerritorialPostsJob($project->id))->handle(app(EventPostGenerator::class));

    $eventPosts = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', 'territorial_event')
        ->get();

    expect($eventPosts->count())->toBeGreaterThan(0);

    $expectedUrl = Storage::disk('public')->url('territorial/events/poster-123.jpg');

    foreach ($eventPosts as $post) {
        expect($post->image_url)->toBe($expectedUrl);
        expect($post->media_type)->toBe('image');
        expect($post->visual_suggestion)->toBeNull();
    }
});

it('leaves post image_url null when event has no image_path', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, [
        'start_date' => '2026-05-10',
        'end_date'   => '2026-05-29',
        'status'     => 'generating',
    ]);

    $event = makeEventForBinding('evt-noimg', null);

    bindEventBindingMocks($event);

    (new GenerateTerritorialPostsJob($project->id))->handle(app(EventPostGenerator::class));

    $eventPosts = Post::withoutGlobalScope('organization')
        ->where('project_id', $project->id)
        ->where('post_type', 'territorial_event')
        ->get();

    expect($eventPosts->count())->toBeGreaterThan(0);

    foreach ($eventPosts as $post) {
        // Senza locandina dall'evento: image_url null, visual_suggestion null.
        // media_type resta 'image' (default DB NOT NULL); l'editor potrà
        // caricare manualmente un asset.
        expect($post->image_url)->toBeNull();
        expect($post->visual_suggestion)->toBeNull();
    }
});
