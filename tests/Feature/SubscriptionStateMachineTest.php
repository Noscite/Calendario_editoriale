<?php

declare(strict_types=1);

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Subscription\Events\SubscriptionActivated;
use App\Domain\Subscription\Events\TrialExpired;
use App\Domain\Subscription\Events\TrialStarted;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Services\SubscriptionStateService;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Event;

// ── Helper ────────────────────────────────────────────────────────────────────

function makeService(): SubscriptionStateService
{
    return app(SubscriptionStateService::class);
}

function freshSub(array $state = []): Subscription
{
    return Subscription::factory()->create($state);
}

// ── Test suite ────────────────────────────────────────────────────────────────

test('new_subscription_starts_in_trial', function () {
    Event::fake([TrialStarted::class]);

    $sub = freshSub();           // factory non chiama startTrial
    makeService()->startTrial($sub);

    $sub->refresh();

    expect($sub->status)->toBe(Subscription::STATUS_TRIAL)
        ->and($sub->trial_started_at)->not->toBeNull()
        ->and($sub->trial_ends_at)->not->toBeNull()
        ->and($sub->daysLeftInTrial())->toBeGreaterThanOrEqual(13)
        ->and($sub->isInTrial())->toBeTrue();

    // Organization.subscription_status sincronizzata
    expect($sub->organization->fresh()->subscription_status)
        ->toBe(OrganizationStatus::Trial);

    Event::assertDispatched(TrialStarted::class, fn ($e) => $e->subscription->id === $sub->id);
});

test('trial_expires_after_14_days', function () {
    Event::fake([TrialExpired::class]);

    // Subscription con trial già scaduto
    $sub = freshSub([
        'status'           => Subscription::STATUS_TRIAL,
        'trial_started_at' => now()->subDays(16),
        'trial_ends_at'    => now()->subDays(2),
    ]);

    expect($sub->isTrialExpired())->toBeTrue()
        ->and($sub->isInTrial())->toBeFalse()
        ->and($sub->daysLeftInTrial())->toBe(0);

    makeService()->expireTrial($sub);
    $sub->refresh();

    expect($sub->status)->toBe(Subscription::STATUS_PENDING_PAYMENT);

    expect($sub->organization->fresh()->subscription_status)
        ->toBe(OrganizationStatus::PendingPayment);

    Event::assertDispatched(TrialExpired::class);
});

test('admin_can_activate_pending_payment_subscription', function () {
    Event::fake([SubscriptionActivated::class]);

    [$user, $org] = createAuthenticatedUser();
    $sub = freshSub([
        'organization_id' => $org->id,
        'status'          => Subscription::STATUS_PENDING_PAYMENT,
    ]);

    makeService()->activatePaidSubscription(
        sub:              $sub,
        months:           3,
        plan:             'standard',
        adminUserId:      $user->id,
        paymentReference: 'BON-2026-04-26-001',
        notes:            'Pagamento ricevuto',
    );

    $sub->refresh();

    expect($sub->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($sub->isActive())->toBeTrue()
        ->and($sub->paid_period_months)->toBe(3)
        ->and($sub->payment_reference)->toBe('BON-2026-04-26-001')
        ->and($sub->activated_by_admin_id)->toBe($user->id)
        ->and($sub->daysLeftInPaidPeriod())->toBeGreaterThan(80);

    expect($sub->organization->fresh()->subscription_status)
        ->toBe(OrganizationStatus::Active);

    Event::assertDispatched(SubscriptionActivated::class);
});

test('cannot_activate_trial_subscription_directly', function () {
    $sub = freshSub(['status' => Subscription::STATUS_TRIAL]);

    expect(fn () => makeService()->activatePaidSubscription(
        sub:              $sub,
        months:           1,
        plan:             'small',
        adminUserId:      1,
        paymentReference: 'TEST',
    ))->toThrow(BusinessException::class)
      ->and(fn () => makeService()->activatePaidSubscription(
          sub:              $sub,
          months:           1,
          plan:             'small',
          adminUserId:      1,
          paymentReference: 'TEST',
      ))->toThrow(fn (BusinessException $e) => $e->errorCode === 'INVALID_STATE_TRANSITION');
});

test('paid_subscription_expires_after_period', function () {
    $sub = freshSub([
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now()->subMonths(2),
        'paid_period_ends_at'   => now()->subHours(1),
        'paid_period_months'    => 1,
    ]);

    expect($sub->isActive())->toBeFalse();

    makeService()->expirePaidSubscription($sub);
    $sub->refresh();

    expect($sub->status)->toBe(Subscription::STATUS_EXPIRED)
        ->and($sub->canAccessApp())->toBeFalse();

    expect($sub->organization->fresh()->subscription_status)
        ->toBe(OrganizationStatus::Expired);
});

test('trial_token_budget_enforced', function () {
    $sub = freshSub([
        'status'                => Subscription::STATUS_TRIAL,
        'trial_started_at'      => now(),
        'trial_ends_at'         => now()->addDays(14),
        'trial_tokens_consumed' => 29_900,
    ]);

    $service = makeService();

    // Consumo entro budget → ok
    $service->incrementTrialTokenUsage($sub, 50);
    expect($sub->fresh()->trial_tokens_consumed)->toBe(29_950);

    // Superamento budget → BusinessException
    expect(fn () => $service->incrementTrialTokenUsage($sub->fresh(), 100))
        ->toThrow(BusinessException::class)
        ->and(fn () => $service->incrementTrialTokenUsage($sub->fresh(), 100))
        ->toThrow(fn (BusinessException $e) => $e->errorCode === 'TRIAL_TOKEN_LIMIT_EXCEEDED');
});

test('trial_calendar_budget_enforced', function () {
    $sub = freshSub([
        'status'                    => Subscription::STATUS_TRIAL,
        'trial_started_at'          => now(),
        'trial_ends_at'             => now()->addDays(14),
        'trial_calendars_generated' => 0,
    ]);

    $service = makeService();

    // Primo calendario → ok
    $service->incrementTrialCalendarUsage($sub);
    expect($sub->fresh()->trial_calendars_generated)->toBe(1);

    // Secondo calendario → BusinessException (budget = 1)
    expect(fn () => $service->incrementTrialCalendarUsage($sub->fresh()))
        ->toThrow(BusinessException::class)
        ->and(fn () => $service->incrementTrialCalendarUsage($sub->fresh()))
        ->toThrow(fn (BusinessException $e) => $e->errorCode === 'TRIAL_CALENDAR_LIMIT_EXCEEDED');
});

test('features_disabled_during_trial', function () {
    $sub = freshSub([
        'status'          => Subscription::STATUS_TRIAL,
        'trial_started_at' => now(),
        'trial_ends_at'   => now()->addDays(14),
    ]);

    // Feature disabilitata in trial
    expect($sub->canUseFeature('social_auto_publish'))->toBeFalse()
        ->and($sub->canUseFeature('social_account_connect'))->toBeFalse()
        ->and($sub->canUseFeature('advanced_export'))->toBeFalse();

    // Feature abilitata in trial
    expect($sub->canUseFeature('ai_calendar_generation'))->toBeTrue()
        ->and($sub->canUseFeature('brand_management'))->toBeTrue();

    // Subscription attiva: tutte le feature abilitate
    $activeSub = freshSub([
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now(),
        'paid_period_ends_at'   => now()->addMonth(),
    ]);

    expect($activeSub->canUseFeature('social_auto_publish'))->toBeTrue()
        ->and($activeSub->canUseFeature('advanced_export'))->toBeTrue();
});

test('update_states_command_processes_all_transitions', function () {
    // Setup: subscription con trial scaduto
    $trialExpired = freshSub([
        'status'           => Subscription::STATUS_TRIAL,
        'trial_started_at' => now()->subDays(16),
        'trial_ends_at'    => now()->subDays(2),
    ]);

    // Setup: subscription attiva con periodo scaduto
    $paidExpired = freshSub([
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now()->subMonths(2),
        'paid_period_ends_at'   => now()->subHours(1),
    ]);

    // Setup: trial in scadenza fra 2 giorni (per notifica)
    $trialExpiring = freshSub([
        'status'           => Subscription::STATUS_TRIAL,
        'trial_started_at' => now()->subDays(12),
        'trial_ends_at'    => now()->addDays(2),
    ]);

    $this->artisan('subscriptions:update-states')->assertSuccessful();

    expect($trialExpired->fresh()->status)->toBe(Subscription::STATUS_PENDING_PAYMENT)
        ->and($paidExpired->fresh()->status)->toBe(Subscription::STATUS_EXPIRED)
        ->and($trialExpiring->fresh()->status)->toBe(Subscription::STATUS_TRIAL); // non ancora scaduto
});
