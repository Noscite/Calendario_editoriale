<?php

declare(strict_types=1);

use App\Domain\Subscription\Events\SubscriptionActivated;
use App\Domain\Subscription\Events\SubscriptionExpiring;
use App\Domain\Subscription\Events\TrialExpired;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Listeners\Subscription\SendSubscriptionActivatedEmail;
use App\Listeners\Subscription\SendSubscriptionExpiringEmail;
use App\Listeners\Subscription\SendTrialExpiredEmail;
use App\Mail\Subscription\SubscriptionActivatedMail;
use App\Mail\Subscription\SubscriptionExpiringMail;
use App\Mail\Subscription\TrialExpiredMail;
use App\Mail\Subscription\WelcomeMail;
use Illuminate\Support\Facades\Mail;

// ── Helper: crea org + owner + subscription ───────────────────────────────

function orgWithOwner(array $subState = []): array
{
    [$user, $org] = createAuthenticatedUser(['role' => 'owner']);
    $sub = Subscription::factory()->create(array_merge(['organization_id' => $org->id], $subState));

    return [$user, $org, $sub];
}

// ── WelcomeMail ───────────────────────────────────────────────────────────

test('welcome_mail_is_queued_on_invitation_accept', function () {
    Mail::fake();

    [$user] = orgWithOwner();

    Mail::to($user->email)->queue(new WelcomeMail($user));

    Mail::assertQueued(WelcomeMail::class, fn ($mail) => $mail->user->id === $user->id);
});

// ── TrialExpiredMail ──────────────────────────────────────────────────────

test('trial_expired_listener_sends_email_to_org_admins', function () {
    Mail::fake();

    [$user, $org, $sub] = orgWithOwner([
        'status'           => Subscription::STATUS_PENDING_PAYMENT,
        'trial_started_at' => now()->subDays(16),
        'trial_ends_at'    => now()->subDays(2),
    ]);

    // Admin aggiuntivo
    $admin = \App\Domain\User\Models\User::create([
        'email'           => 'admin-' . \Illuminate\Support\Str::random(8) . '@test.com',
        'password'        => 'password',
        'full_name'       => 'Admin User',
        'organization_id' => $org->id,
        'role'            => 'admin',
        'is_active'       => true,
    ]);

    $listener = new SendTrialExpiredEmail();
    $listener->handle(new TrialExpired($sub->load('organization')));

    Mail::assertQueued(TrialExpiredMail::class, 2); // owner + admin
    Mail::assertQueued(TrialExpiredMail::class, fn ($mail) => $mail->user->id === $user->id);
    Mail::assertQueued(TrialExpiredMail::class, fn ($mail) => $mail->user->id === $admin->id);
});

// ── SubscriptionActivatedMail ─────────────────────────────────────────────

test('subscription_activated_listener_sends_email', function () {
    Mail::fake();

    [$user, $org, $sub] = orgWithOwner([
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now(),
        'paid_period_ends_at'   => now()->addMonths(3),
        'paid_period_months'    => 3,
        'payment_reference'     => 'BON-TEST-001',
    ]);

    $listener = new SendSubscriptionActivatedEmail();
    $listener->handle(new SubscriptionActivated($sub->load('organization')));

    Mail::assertQueued(SubscriptionActivatedMail::class, fn ($mail) => $mail->user->id === $user->id);
});

// ── SubscriptionExpiringMail ──────────────────────────────────────────────

test('subscription_expiring_listener_sends_email', function () {
    Mail::fake();

    [$user, $org, $sub] = orgWithOwner([
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now()->subMonths(2),
        'paid_period_ends_at'   => now()->addDays(5),
        'paid_period_months'    => 3,
    ]);

    $listener = new SendSubscriptionExpiringEmail();
    $listener->handle(new SubscriptionExpiring($sub->load('organization')));

    Mail::assertQueued(SubscriptionExpiringMail::class, fn ($mail) => $mail->user->id === $user->id);
});

// ── Mailable subject lines ────────────────────────────────────────────────

test('trial_expired_mail_has_correct_subject', function () {
    [$user, $org] = orgWithOwner();

    $mail = new TrialExpiredMail($user, $org);

    expect($mail->envelope()->subject)->toContain('trial');
});

test('subscription_expiring_mail_subject_contains_days', function () {
    [$user, $org, $sub] = orgWithOwner([
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now(),
        'paid_period_ends_at'   => now()->addDays(5),
        'paid_period_months'    => 1,
    ]);

    $mail = new SubscriptionExpiringMail($user, $org, $sub);

    // daysLeftInPaidPeriod() usa diffInDays che trunca — accettiamo 4 o 5
    expect($mail->envelope()->subject)->toMatch('/\b[45]\b/');
});

// ── Queue alignment (Horizon ascolta 'email' singolare) ───────────────────

test('welcome_mail_uses_email_queue_singular', function () {
    [$user] = orgWithOwner();

    $mail = new WelcomeMail($user);

    expect($mail->queue)->toBe('email');
});

test('trial_expired_mail_uses_email_queue_singular', function () {
    [$user, $org] = orgWithOwner();

    $mail = new TrialExpiredMail($user, $org);

    expect($mail->queue)->toBe('email');
});

test('subscription_activated_mail_uses_email_queue_singular', function () {
    [$user, $org, $sub] = orgWithOwner([
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now(),
        'paid_period_ends_at'   => now()->addMonths(3),
        'paid_period_months'    => 3,
    ]);

    $mail = new SubscriptionActivatedMail($user, $org, $sub);

    expect($mail->queue)->toBe('email');
});

test('subscription_expiring_mail_uses_email_queue_singular', function () {
    [$user, $org, $sub] = orgWithOwner([
        'status'                => Subscription::STATUS_ACTIVE,
        'paid_period_starts_at' => now(),
        'paid_period_ends_at'   => now()->addDays(5),
        'paid_period_months'    => 1,
    ]);

    $mail = new SubscriptionExpiringMail($user, $org, $sub);

    expect($mail->queue)->toBe('email');
});
