<?php

declare(strict_types=1);

namespace App\Domain\Generation\Data;

use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO per la richiesta di generazione AI di post — corrisponde a AIPostGenerateRequest (Python).
 */
final class AIGenerateRequestData extends Data
{
    public function __construct(
        #[Required, Exists('projects', 'id')]
        public readonly int $project_id,

        #[Required, DateFormat('Y-m-d')]
        public readonly string $start_date,

        #[Required, DateFormat('Y-m-d')]
        public readonly string $end_date,

        #[Required]
        public readonly string $brief,

        /** @var array<string> */
        public readonly array $platforms = [],

        public readonly bool $ai_decide_platforms = false,

        public readonly Optional|int $num_posts = new Optional(),

        public readonly bool $ai_decide_num_posts = false,

        public readonly string $pillar = '',
    ) {}
}
