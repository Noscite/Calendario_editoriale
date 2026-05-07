<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Facades\Route;

// Registra una rotta finta protetta dal middleware su una feature gated
beforeEach(function () {
    Route::middleware(['auth:sanctum', 'subscription.active', 'check.feature:social_account_connect'])
        ->post('/_test/feature-gate', fn () => response()->json(['ok' => true]));
});

test('trial subscription returns 403 with structured payload on gated feature', function () {
    [$user] = createAuthenticatedUser();

    Subscription::factory()->inTrial()->create([
        'organization_id' => $user->organization_id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/_test/feature-gate');

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FEATURE_DISABLED_DURING_TRIAL')
        ->assertJsonPath('error.feature', 'social_account_connect')
        ->assertJsonStructure(['error' => ['code', 'feature', 'message']]);
});

test('active subscription passes through gated feature middleware', function () {
    [$user] = createAuthenticatedUser();

    Subscription::factory()->active()->create([
        'organization_id' => $user->organization_id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/_test/feature-gate');

    $response->assertOk()->assertJson(['ok' => true]);
});

test('system tenant bypasses feature gate even without subscription', function () {
    [$user, $org] = createAuthenticatedUser();
    $org->update(['is_system_tenant' => true]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/_test/feature-gate');

    $response->assertOk()->assertJson(['ok' => true]);
});

test('non-gated feature passes during trial', function () {
    [$user] = createAuthenticatedUser();

    Subscription::factory()->inTrial()->create([
        'organization_id' => $user->organization_id,
    ]);

    Route::middleware(['auth:sanctum', 'subscription.active', 'check.feature:non_existing_feature'])
        ->post('/_test/feature-gate-open', fn () => response()->json(['ok' => true]));

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/_test/feature-gate-open');

    $response->assertOk();
});
