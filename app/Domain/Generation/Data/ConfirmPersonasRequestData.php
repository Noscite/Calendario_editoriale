<?php

declare(strict_types=1);

namespace App\Domain\Generation\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO per conferma dei personas — corrisponde a ConfirmPersonasRequest (Python).
 */
final class ConfirmPersonasRequestData extends Data
{
    public function __construct(
        /** @var array<string, mixed>|Optional */
        public readonly Optional|array $personas = new Optional(),
    ) {}
}
