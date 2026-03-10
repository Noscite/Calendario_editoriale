<?php

declare(strict_types=1);

namespace App\Domain\Generation\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * DTO per rigenerazione di un singolo post — corrisponde a RegeneratePostRequest (Python).
 */
final class RegeneratePostRequestData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $user_prompt,
    ) {}
}
