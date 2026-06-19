<?php

declare(strict_types=1);

use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Services\FacebookPublisher;
use Illuminate\Support\Facades\Http;

/**
 * Copertura del publishing Facebook (Page /photos + /feed).
 * I fixture sono modelli NON salvati (no DB): il publisher legge solo
 * external_account_id, access_token e i campi del post.
 *
 * La versione Graph API è pinnata a v25.0 via config: gli assert verificano
 * che le chiamate vadano a graph.facebook.com/v25.0 → catturano regressioni
 * (versione hardcoded, endpoint cambiato).
 */
beforeEach(function () {
    config(['services.meta.graph_version' => 'v25.0']);
});

function buildFbConnection(): SocialConnection
{
    return new SocialConnection([
        'external_account_id' => 'PAGE_123',
        'access_token'        => 'tok_fb',
    ]);
}

function buildFbPost(array $overrides = []): Post
{
    return new Post(array_merge([
        'content'  => 'Ciao mondo',
        'hashtags' => ['test', 'kalendarium'],
    ], $overrides));
}

it('FB: pubblica singola immagine su /photos (v25)', function () {
    Http::fake([
        'graph.facebook.com/v25.0/*/photos' => Http::response(['id' => 'PH_1', 'post_id' => 'PAGE_123_POST_1'], 200),
    ]);

    $result = (new FacebookPublisher())->publish(
        buildFbPost(['image_url' => 'https://example.com/a.jpg']),
        buildFbConnection(),
    );

    expect($result['success'])->toBeTrue();
    expect($result['external_post_id'])->toBe('PAGE_123_POST_1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v25.0/PAGE_123/photos'));
});

it('FB: pubblica carousel (photos unpublished + feed con attached_media)', function () {
    Http::fake([
        'graph.facebook.com/v25.0/*/photos' => Http::response(['id' => 'PH_X'], 200),
        'graph.facebook.com/v25.0/*/feed'   => Http::response(['id' => 'FEED_1'], 200),
    ]);

    $result = (new FacebookPublisher())->publish(
        buildFbPost(['is_carousel' => true, 'carousel_images' => ['https://e.com/1.jpg', 'https://e.com/2.jpg']]),
        buildFbConnection(),
    );

    expect($result['success'])->toBeTrue();
    expect($result['external_post_id'])->toBe('FEED_1');
    // upload foto unpublished + creazione feed, tutte su v25
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v25.0/PAGE_123/photos'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v25.0/PAGE_123/feed'));
});

it('FB: pubblica solo testo su /feed (v25)', function () {
    Http::fake([
        'graph.facebook.com/v25.0/*/feed' => Http::response(['id' => 'FEED_TXT'], 200),
    ]);

    $result = (new FacebookPublisher())->publish(buildFbPost(), buildFbConnection());

    expect($result['success'])->toBeTrue();
    expect($result['external_post_id'])->toBe('FEED_TXT');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v25.0/PAGE_123/feed'));
});

it('FB: errore API → success false con messaggio, nessuna eccezione', function () {
    Http::fake([
        'graph.facebook.com/v25.0/*/feed' => Http::response(['error' => ['message' => 'Permesso negato']], 400),
    ]);

    $result = (new FacebookPublisher())->publish(buildFbPost(), buildFbConnection());

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('Permesso negato');
});
