<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO di input per l'aggiornamento di un'Organization — corrisponde a OrganizationSaaSUpdate (Python).
 */
final class UpdateOrganizationData extends Data
{
    public function __construct(
        #[Max(255)]
        public readonly Optional|string $name = new Optional(),

        #[Email]
        public readonly Optional|string $email = new Optional(),

        public readonly Optional|string $phone = new Optional(),

        #[Max(50)]
        public readonly Optional|string $vat_number = new Optional(),

        public readonly Optional|string $address = new Optional(),

        public readonly Optional|int $plan_id = new Optional(),

        #[In(['trial', 'active', 'suspended', 'cancelled', 'expired'])]
        public readonly Optional|string $subscription_status = new Optional(),

        public readonly Optional|string $trial_ends_at = new Optional(),

        public readonly Optional|string $subscription_starts_at = new Optional(),

        public readonly Optional|string $subscription_ends_at = new Optional(),

        /** @var array<string, mixed>|Optional */
        public readonly Optional|array $custom_limits = new Optional(),

        public readonly Optional|string $notes = new Optional(),

        public readonly Optional|bool $is_active = new Optional(),
    ) {}
}
