<?php

declare(strict_types=1);

namespace App\Domain\User\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO di input per la creazione di un User — corrisponde a UserCreate (Python, admin version).
 */
final class CreateUserData extends Data
{
    public function __construct(
        #[Required, Email]
        public readonly string $email,

        #[Required, Min(8)]
        public readonly string $password,

        #[Max(255)]
        public readonly Optional|string $full_name = new Optional(),

        #[In(['superuser', 'admin', 'editor', 'viewer'])]
        public readonly string $role = 'editor',

        public readonly Optional|int $organization_id = new Optional(),
    ) {}
}
