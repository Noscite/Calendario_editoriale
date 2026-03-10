<?php

declare(strict_types=1);

namespace App\Domain\User\Data;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * DTO per il cambio password — corrisponde a PasswordChange (Python).
 */
final class ChangePasswordData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $current_password,

        #[Required, Min(8)]
        public readonly string $new_password,
    ) {}
}
