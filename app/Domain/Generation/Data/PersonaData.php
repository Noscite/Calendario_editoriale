<?php

declare(strict_types=1);

namespace App\Domain\Generation\Data;

use Spatie\LaravelData\Data;

/**
 * DTO per una buyer persona — salvata in Project.buyer_personas (JSON).
 *
 * Corrisponde alla struttura dei personas generati dall'AI e confermati dall'utente.
 */
final class PersonaData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $age_range = null,
        public readonly ?string $occupation = null,
        /** @var array<string> */
        public readonly array $goals = [],
        /** @var array<string> */
        public readonly array $pain_points = [],
        /** @var array<string> */
        public readonly array $preferred_platforms = [],
        public readonly ?string $content_preferences = null,
        public readonly ?string $description = null,
    ) {}
}
