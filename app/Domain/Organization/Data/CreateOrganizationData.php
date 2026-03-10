<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO di input per la creazione di un'Organization — corrisponde a OrganizationCreate (Python).
 */
final class CreateOrganizationData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,

        #[Required, Email]
        public readonly string $email,

        #[Required, Exists('subscription_plans', 'id')]
        public readonly int $plan_id,

        public readonly Optional|string $phone = new Optional(),

        #[Max(50)]
        public readonly Optional|string $vat_number = new Optional(),

        public readonly Optional|string $address = new Optional(),

        /** @var array<string, mixed>|Optional */
        public readonly Optional|array $custom_limits = new Optional(),

        public readonly Optional|string $notes = new Optional(),
    ) {}
}
