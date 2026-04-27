<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $org = Organization::create([
            'name'                => 'Org ' . Str::random(8),
            'slug'                => 'org-' . Str::random(12),
            'email'               => $this->faker->unique()->safeEmail(),
            'subscription_status' => 'trial',
            'is_active'           => true,
        ]);

        return [
            'organization_id'         => $org->id,
            'status'                  => Subscription::STATUS_TRIAL,
            'trial_tokens_consumed'   => 0,
            'trial_calendars_generated' => 0,
        ];
    }

    // ── Stati predefiniti ──────────────────────────────────────────

    public function inTrial(): static
    {
        return $this->state(fn () => [
            'status'          => Subscription::STATUS_TRIAL,
            'trial_started_at' => now(),
            'trial_ends_at'   => now()->addDays(14),
        ]);
    }

    public function trialExpired(): static
    {
        return $this->state(fn () => [
            'status'           => Subscription::STATUS_TRIAL,
            'trial_started_at' => now()->subDays(16),
            'trial_ends_at'    => now()->subDays(2),
        ]);
    }

    public function pendingPayment(): static
    {
        return $this->state(fn () => [
            'status'           => Subscription::STATUS_PENDING_PAYMENT,
            'trial_started_at' => now()->subDays(16),
            'trial_ends_at'    => now()->subDays(2),
        ]);
    }

    public function active(int $months = 1): static
    {
        return $this->state(fn () => [
            'status'                => Subscription::STATUS_ACTIVE,
            'paid_period_starts_at' => now(),
            'paid_period_ends_at'   => now()->addMonths($months),
            'paid_period_months'    => $months,
            'activated_at'          => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status'                => Subscription::STATUS_EXPIRED,
            'paid_period_starts_at' => now()->subMonths(2),
            'paid_period_ends_at'   => now()->subDays(1),
            'paid_period_months'    => 1,
        ]);
    }
}
