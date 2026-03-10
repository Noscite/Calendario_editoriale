<?php

declare(strict_types=1);

namespace App\Domain\Generation\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * DTO per rigenerazione dei personas — corrisponde a RegeneratePersonasRequest (Python).
 */
final class RegeneratePersonasRequestData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $feedback,
    ) {}
}
