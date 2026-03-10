<?php

declare(strict_types=1);

namespace App\Domain\User\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO di input per l'aggiornamento di un User — corrisponde a UserUpdate + ProfileUpdate (Python).
 */
final class UpdateUserData extends Data
{
    public function __construct(
        #[Max(255)]
        public readonly Optional|string $full_name = new Optional(),

        #[In(['superuser', 'admin', 'editor', 'viewer'])]
        public readonly Optional|string $role = new Optional(),

        public readonly Optional|bool $is_active = new Optional(),

        public readonly Optional|int $organization_id = new Optional(),

        // Profilo esteso (da ProfileUpdate)
        public readonly Optional|string $phone = new Optional(),

        public readonly Optional|string $company = new Optional(),

        public readonly Optional|string $address = new Optional(),

        public readonly Optional|string $city = new Optional(),

        public readonly Optional|string $country = new Optional(),

        #[Max(50)]
        public readonly Optional|string $vat_number = new Optional(),

        public readonly Optional|string $notes = new Optional(),
    ) {}
}
