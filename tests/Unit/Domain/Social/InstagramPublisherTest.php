<?php

declare(strict_types=1);

use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Services\InstagramPublisher;
use Illuminate\Support\Facades\Http;

/**
 * Copertura del publishing Instagram (container flow: POST /media → /media_publish;
 * reel con polling status_code).
 *
 * NB: il publisher usa sleep() nativo (PUBLISH_DELAY/REEL_POLL_INTERVAL = 5s),
 * non mockabile → i test single/carousel/reel dormono ~5s ciascuno. Il reel
 * fa break al primo poll FINISHED (1 sola sleep).
 *
 * Versione Graph API pinnata a v25.0 via config → gli assert verificano che le
 * chiamate vadano a graph.facebook.com/v25.0.
 */
beforeEach(function () {
    config(['services.meta.graph_version' => 'v25.0']);
});

function buildIgConnection(): SocialConnection
{
    return new SocialConnection([
        'external_account_id' => 'IG_123',
        'access_token'        => 'tok_ig',
    ]);
}

function buildIgPost(array $overrides = []): Post
{
    return new Post(array_merge([
        'content'  => 'Ciao IG',
        'hashtags' => ['test'],
    ], $overrides));
}

/** Fake del container flow: media_publish → media → catch-all (status poll). */
function fakeIgContainerFlow(string $status = 'FINISHED'): void
{
    Http::fake([
        'graph.facebook.com/v25.0/*/media_publish' => Http::response(['id' => 'IG_MEDIA_1'], 200),
        'graph.facebook.com/v25.0/*/media'         => Http::response(['id' => 'CONTAINER_1'], 200),
        'graph.facebook.com/v25.0/*'               => Http::response(['status_code' => $status], 200),
    ]);
}

it('IG: pubblica singola immagine (container → publish, v25)', function () {
    fakeIgContainerFlow();

    $result = (new InstagramPublisher())->publish(
        buildIgPost(['image_url' => 'https://example.com/a.jpg']),
        buildIgConnection(),
    );

    expect($result['success'])->toBeTrue();
    expect($result['external_post_id'])->toBe('IG_MEDIA_1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v25.0/IG_123/media'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v25.0/IG_123/media_publish'));
});

it('IG: pubblica carousel (N children → parent CAROUSEL → publish)', function () {
    fakeIgContainerFlow();

    $result = (new InstagramPublisher())->publish(
        buildIgPost(['is_carousel' => true, 'carousel_images' => ['https://e.com/1.jpg', 'https://e.com/2.jpg']]),
        buildIgConnection(),
    );

    expect($result['success'])->toBeTrue();
    expect($result['external_post_id'])->toBe('IG_MEDIA_1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v25.0/IG_123/media_publish'));
});

it('IG: pubblica reel con polling status FINISHED (v25)', function () {
    fakeIgContainerFlow('FINISHED');

    $result = (new InstagramPublisher())->publish(
        buildIgPost(['media_type' => 'reel', 'image_url' => 'https://example.com/v.mp4']),
        buildIgConnection(),
    );

    expect($result['success'])->toBeTrue();
    expect($result['external_post_id'])->toBe('IG_MEDIA_1');
    // il container REELS e il polling status passano da v25
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v25.0/IG_123/media'));
});

it('IG: errore creazione container → success false, nessuna eccezione', function () {
    Http::fake([
        'graph.facebook.com/v25.0/*/media' => Http::response(['error' => ['message' => 'IG container rifiutato']], 400),
    ]);

    $result = (new InstagramPublisher())->publish(
        buildIgPost(['image_url' => 'https://example.com/a.jpg']),
        buildIgConnection(),
    );

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('IG container rifiutato');
});
