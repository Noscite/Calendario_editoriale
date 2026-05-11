<?php

declare(strict_types=1);

use App\Domain\Generation\Services\AnthropicApiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.anthropic.api_key', 'test-key');
});

it('formats mcp servers correctly for Anthropic body', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{}']],
            'usage'   => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200),
    ]);

    $client = new AnthropicApiClient();
    $client->call('test prompt', 100, 'claude-sonnet-4-6', null, [
        ['name' => 'Catalogo prodotti', 'url' => 'https://mcp.example.com/sse', 'api_key' => 'tok-123'],
        ['name' => 'CRM',                'url' => 'https://crm.example.com/sse', 'api_key' => null],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();
        if (! isset($body['mcp_servers']) || count($body['mcp_servers']) !== 2) {
            return false;
        }
        $first = $body['mcp_servers'][0];
        $second = $body['mcp_servers'][1];
        return $first['type'] === 'url'
            && $first['url'] === 'https://mcp.example.com/sse'
            && $first['name'] === 'Catalogo prodotti'
            && ($first['authorization_token'] ?? null) === 'tok-123'
            && $second['name'] === 'CRM'
            && ! isset($second['authorization_token']);  // null → omitted
    });
});

it('adds mcp-client beta header when mcp_servers non empty', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{}']],
            'usage'   => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200),
    ]);

    $client = new AnthropicApiClient();
    $client->call('prompt', 100, 'claude-sonnet-4-6', null, [
        ['name' => 'X', 'url' => 'https://x.example.com/sse', 'api_key' => null],
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('anthropic-beta', 'mcp-client-2025-04-04'));
});

it('does NOT add mcp_servers in body when empty array', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{}']],
            'usage'   => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200),
    ]);

    $client = new AnthropicApiClient();
    $client->call('prompt', 100, 'claude-sonnet-4-6', null, []);

    Http::assertSent(fn ($request) => ! isset($request->data()['mcp_servers']));
});

it('filters out mcp items missing url or name', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{}']],
            'usage'   => ['input_tokens' => 10, 'output_tokens' => 5],
        ], 200),
    ]);

    $client = new AnthropicApiClient();
    $client->call('prompt', 100, 'claude-sonnet-4-6', null, [
        ['name' => 'OK',     'url' => 'https://ok.example.com/sse', 'api_key' => null],
        ['name' => '',       'url' => 'https://x.example.com/sse',  'api_key' => null], // missing name
        ['name' => 'No URL', 'url' => '',                            'api_key' => null], // missing url
    ]);

    Http::assertSent(function ($request) {
        $servers = $request->data()['mcp_servers'] ?? [];
        return count($servers) === 1 && $servers[0]['name'] === 'OK';
    });
});

it('callCached includes mcp_servers + concat beta headers', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{}']],
            'usage'   => [
                'input_tokens'                => 10,
                'output_tokens'               => 5,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens'     => 0,
            ],
        ], 200),
    ]);

    $client = new AnthropicApiClient();
    $client->callCached(
        'static brand context',
        'dynamic post batch',
        100,
        'claude-sonnet-4-6',
        null,
        null,
        [['name' => 'X', 'url' => 'https://x.example.com/sse', 'api_key' => 'k']],
    );

    Http::assertSent(function ($request) {
        $body = $request->data();
        $hasMcp = isset($body['mcp_servers']) && count($body['mcp_servers']) === 1;

        $beta = $request->header('anthropic-beta')[0] ?? '';
        $hasBoth = str_contains($beta, 'prompt-caching') && str_contains($beta, 'mcp-client');

        return $hasMcp && $hasBoth;
    });
});
