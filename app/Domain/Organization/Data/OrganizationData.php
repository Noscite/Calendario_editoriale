<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use App\Domain\Organization\Models\Organization;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * DTO di output per un'Organization — corrisponde a OrganizationSaaSResponse (Python).
 */
final class OrganizationData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $slug,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $vat_number,
        public readonly ?string $address,
        public readonly ?int $plan_id,
        public readonly ?string $plan_name,
        public readonly ?string $plan_display_name,
        public readonly string $subscription_status = 'trial',
        public readonly ?CarbonImmutable $trial_ends_at = null,
        public readonly ?CarbonImmutable $subscription_starts_at = null,
        public readonly ?CarbonImmutable $subscription_ends_at = null,
        public readonly ?int $effective_max_brands = null,
        public readonly ?int $effective_max_users = null,
        public readonly ?int $effective_monthly_calendars = null,
        public readonly ?int $effective_monthly_tokens = null,
        public readonly ?int $effective_monthly_images = null,
        public readonly int $brands_count = 0,
        public readonly int $users_count = 0,
        public readonly ?array $custom_limits = null,
        public readonly ?string $notes = null,
        public readonly bool $is_active = true,
        public readonly ?CarbonImmutable $created_at = null,
    ) {}

    /**
     * Crea il DTO dal modello con conteggi e limiti effettivi.
     *
     * @param  array<string, int>|null  $effectiveLimits  Limiti calcolati da BillingService
     */
    public static function fromModel(Organization $org, ?array $effectiveLimits = null): self
    {
        $plan = $org->relationLoaded('plan') ? $org->plan : null;

        return new self(
            id: $org->id,
            name: $org->name,
            slug: $org->slug,
            email: $org->email,
            phone: $org->phone,
            vat_number: $org->vat_number,
            address: $org->address,
            plan_id: $org->plan_id,
            plan_name: $plan?->name,
            plan_display_name: $plan?->display_name,
            subscription_status: $org->subscription_status instanceof \BackedEnum
                ? $org->subscription_status->value
                : ($org->subscription_status ?? 'trial'),
            trial_ends_at: $org->trial_ends_at ? CarbonImmutable::instance($org->trial_ends_at) : null,
            subscription_starts_at: $org->subscription_starts_at ? CarbonImmutable::instance($org->subscription_starts_at) : null,
            subscription_ends_at: $org->subscription_ends_at ? CarbonImmutable::instance($org->subscription_ends_at) : null,
            effective_max_brands: $effectiveLimits['max_brands'] ?? $plan?->max_brands,
            effective_max_users: $effectiveLimits['max_users'] ?? $plan?->max_users,
            effective_monthly_calendars: $effectiveLimits['monthly_calendar_generations'] ?? $plan?->monthly_calendar_generations,
            effective_monthly_tokens: $effectiveLimits['monthly_text_tokens'] ?? $plan?->monthly_text_tokens,
            effective_monthly_images: $effectiveLimits['monthly_images'] ?? $plan?->monthly_images,
            brands_count: $org->brands_count ?? 0,
            users_count: $org->users_count ?? 0,
            custom_limits: $org->custom_limits,
            notes: $org->notes,
            is_active: (bool) $org->is_active,
            created_at: $org->created_at ? CarbonImmutable::instance($org->created_at) : null,
        );
    }
}
