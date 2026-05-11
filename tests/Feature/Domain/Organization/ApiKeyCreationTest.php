<?php

declare(strict_types=1);

use App\Domain\Organization\Models\ApiKey;
use Illuminate\Support\Str;

it('creates an ApiKey with 12-char prefix without truncation error', function () {
    [$user, $org] = createAuthenticatedUser();

    $rawKey    = 'nsc_' . Str::random(43);
    $keyHash   = ApiKey::hashKey($rawKey);
    $keyPrefix = substr($rawKey, 0, 12);

    $apiKey = ApiKey::create([
        'organization_id' => $org->id,
        'user_id'         => $user->id,
        'name'            => 'Test MCP key',
        'key_hash'        => $keyHash,
        'key_prefix'      => $keyPrefix,
        'scopes'          => ['read', 'write'],
        'is_active'       => true,
    ]);

    expect($apiKey->id)->toBeInt();
    expect($apiKey->key_prefix)->toBe($keyPrefix);
    expect(strlen($apiKey->key_prefix))->toBe(12);
    expect($apiKey->scopes)->toBe(['read', 'write']);
});

it('POST /api/api-keys/ creates a new API key successfully (regression 22001)', function () {
    [$user] = createAuthenticatedUser();

    $response = $this->actingAs($user)
        ->postJson('/api/api-keys/', [
            'name'   => 'Test API Key',
            'scopes' => ['read'],
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'id',
        'name',
        'key',
        'key_prefix',
        'scopes',
        'message',
    ]);

    $body = $response->json();
    expect($body['key'])->toStartWith('nsc_');
    expect($body['key_prefix'])->toStartWith('nsc_');
    expect(strlen($body['key_prefix']))->toBe(12);
});
