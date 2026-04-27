<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Services\SubscriptionStateService;
use App\Domain\User\Models\User;

// ── Test sulla SubscriptionStateService (logica che alimenta le Filament actions) ──

test('filament_activate_action_calls_service', function () {
    [$adminUser, $org] = createAuthenticatedUser();

    $sub = Subscription::factory()->create([
        'organization_id' => $org->id,
        'status'          => Subscription::STATUS_PENDING_PAYMENT,
    ]);

    $service = app(SubscriptionStateService::class);
    $service->activatePaidSubscription(
        sub:              $sub,
        months:           6,
        plan:             'standard',
        adminUserId:      $adminUser->id,
        paymentReference: 'BON-2026-04-26-FIL',
        notes:            'Attivato da Filament test',
    );

    $sub->refresh();

    expect($sub->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($sub->paid_period_months)->toBe(6)
        ->and($sub->payment_reference)->toBe('BON-2026-04-26-FIL')
        ->and($sub->activated_by_admin_id)->toBe($adminUser->id);
});

test('filament_extend_trial_action_calls_service', function () {
    [, $org] = createAuthenticatedUser();

    $sub = Subscription::factory()->create([
        'organization_id' => $org->id,
        'status'          => Subscription::STATUS_PENDING_PAYMENT,
        'trial_started_at' => now()->subDays(16),
        'trial_ends_at'   => now()->subDays(2),
    ]);

    $service = app(SubscriptionStateService::class);
    $service->extendTrial($sub, 7);

    $sub->refresh();

    expect($sub->status)->toBe(Subscription::STATUS_TRIAL)
        ->and($sub->trial_ends_at)->not->toBeNull()
        ->and($sub->trial_ends_at->isFuture())->toBeTrue();
});

test('filament_cancel_action_calls_service', function () {
    [, $org] = createAuthenticatedUser();

    $sub = Subscription::factory()->create([
        'organization_id' => $org->id,
        'status'          => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now(),
        'paid_period_ends_at'   => now()->addMonths(3),
    ]);

    $service = app(SubscriptionStateService::class);
    $service->cancel($sub, 'Richiesta cliente via email');

    $sub->refresh();

    expect($sub->status)->toBe(Subscription::STATUS_CANCELLED)
        ->and($sub->payment_notes)->toContain('Richiesta cliente via email');
});

test('extend_trial_from_pending_payment_state', function () {
    [, $org] = createAuthenticatedUser();

    $sub = Subscription::factory()->create([
        'organization_id' => $org->id,
        'status'          => Subscription::STATUS_PENDING_PAYMENT,
        'trial_ends_at'   => now()->subDays(5),
    ]);

    $service = app(SubscriptionStateService::class);
    $service->extendTrial($sub, 14);

    $sub->refresh();

    expect($sub->status)->toBe(Subscription::STATUS_TRIAL)
        ->and(now()->diffInDays($sub->trial_ends_at, absolute: false))->toBeGreaterThan(10);
});

test('extend_trial_from_active_state_throws', function () {
    [, $org] = createAuthenticatedUser();

    $sub = Subscription::factory()->create([
        'organization_id'       => $org->id,
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now(),
        'paid_period_ends_at'   => now()->addMonth(),
    ]);

    expect(fn () => app(SubscriptionStateService::class)->extendTrial($sub))
        ->toThrow(\App\Exceptions\BusinessException::class);
});
