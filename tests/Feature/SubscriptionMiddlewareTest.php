<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Facades\Route;

// ── Helper: registra una rotta di test protetta dal middleware ──────────────

beforeEach(function () {
    Route::middleware(['auth:sanctum', 'subscription.active'])
        ->get('/_test/subscription-check', fn () => response()->json(['ok' => true]));
});

// ── Test suite ──────────────────────────────────────────────────────────────

test('active_subscription_passes_middleware', function () {
    [$user] = createAuthenticatedUser();

    Subscription::factory()->create([
        'organization_id'       => $user->organization_id,
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now()->subDay(),
        'paid_period_ends_at'   => now()->addMonth(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/_test/subscription-check');

    $response->assertOk()->assertJson(['ok' => true]);
});

test('trial_subscription_passes_middleware', function () {
    [$user] = createAuthenticatedUser();

    Subscription::factory()->create([
        'organization_id' => $user->organization_id,
        'status'          => Subscription::STATUS_TRIAL,
        'trial_started_at' => now()->subDay(),
        'trial_ends_at'   => now()->addDays(10),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/_test/subscription-check');

    $response->assertOk();
});

test('expired_subscription_returns_402', function () {
    [$user] = createAuthenticatedUser();

    Subscription::factory()->create([
        'organization_id'       => $user->organization_id,
        'status'                => Subscription::STATUS_EXPIRED,
        'paid_period_starts_at' => now()->subMonths(2),
        'paid_period_ends_at'   => now()->subDay(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/_test/subscription-check');

    $response->assertStatus(402)
        ->assertJsonPath('error.code', 'SUBSCRIPTION_INACTIVE');
});

test('pending_payment_subscription_returns_402', function () {
    [$user] = createAuthenticatedUser();

    Subscription::factory()->create([
        'organization_id' => $user->organization_id,
        'status'          => Subscription::STATUS_PENDING_PAYMENT,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/_test/subscription-check');

    $response->assertStatus(402);
});

test('system_tenant_bypasses_middleware', function () {
    [$user, $org] = createAuthenticatedUser();

    // Nessuna subscription ma is_system_tenant
    $org->update(['is_system_tenant' => true]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/_test/subscription-check');

    // Anche senza subscription, il system tenant passa
    $response->assertOk();
});

test('unauthenticated_request_returns_401_not_402', function () {
    $response = $this->getJson('/_test/subscription-check');

    $response->assertUnauthorized();
});
