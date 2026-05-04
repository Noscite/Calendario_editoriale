<?php

declare(strict_types=1);

use App\Domain\Document\Services\OpenAiEmbeddingClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.openai.api_key', 'sk-test-fake-key');
});

/**
 * Genera una response OpenAI realistica per N input.
 * Ogni embedding è un array di 1536 float (qui semplificato a [index, 0.0, 0.0, ...]).
 */
function fakeEmbeddingResponse(int $count, int $dim = 1536): array
{
    $data = [];
    for ($i = 0; $i < $count; $i++) {
        $vec    = array_fill(0, $dim, 0.0);
        $vec[0] = (float) $i;
        $data[] = [
            'object'    => 'embedding',
            'index'     => $i,
            'embedding' => $vec,
        ];
    }

    return [
        'object' => 'list',
        'data'   => $data,
        'model'  => 'text-embedding-3-small',
        'usage'  => [
            'prompt_tokens' => $count * 10,
            'total_tokens'  => $count * 10,
        ],
    ];
}

it('embeds a single text', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response(fakeEmbeddingResponse(1), 200),
    ]);

    $vectors = app(OpenAiEmbeddingClient::class)->embed(['hello world']);

    expect($vectors)->toHaveCount(1);
    expect($vectors[0])->toHaveCount(1536);
    expect((float) $vectors[0][0])->toBe(0.0);
});

it('embeds multiple texts in one call preserving order', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response(fakeEmbeddingResponse(5), 200),
    ]);

    $texts   = ['a', 'b', 'c', 'd', 'e'];
    $vectors = app(OpenAiEmbeddingClient::class)->embed($texts);

    expect($vectors)->toHaveCount(5);
    foreach ($vectors as $i => $vec) {
        expect((float) $vec[0])->toBe((float) $i);
    }

    Http::assertSentCount(1);
});

it('splits into batches when over 100', function () {
    Http::fakeSequence('api.openai.com/v1/embeddings')
        ->push(fakeEmbeddingResponse(100), 200)
        ->push(fakeEmbeddingResponse(50), 200);

    $texts   = array_fill(0, 150, 'text');
    $vectors = app(OpenAiEmbeddingClient::class)->embed($texts);

    expect($vectors)->toHaveCount(150);
    Http::assertSentCount(2);
});

it('retries on 429 and succeeds', function () {
    Http::fakeSequence('api.openai.com/v1/embeddings')
        ->push(['error' => 'rate_limited'], 429, ['Retry-After' => '1'])
        ->push(fakeEmbeddingResponse(1), 200);

    $vectors = app(OpenAiEmbeddingClient::class)->embed(['hi']);

    expect($vectors)->toHaveCount(1);
    Http::assertSentCount(2);
});

it('throws after max retries', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response(['error' => 'server_error'], 500),
    ]);

    expect(fn () => app(OpenAiEmbeddingClient::class)->embed(['boom']))
        ->toThrow(RuntimeException::class);
});
