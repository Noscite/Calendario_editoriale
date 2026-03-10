<?php

declare(strict_types=1);

namespace App\Domain\Generation\Data;

use Spatie\LaravelData\Data;

/**
 * DTO per il progresso della generazione — usato nei WebSocket/SSE events.
 */
final class GenerationProgressData extends Data
{
    public function __construct(
        public readonly string $status,
        public readonly int $post_count = 0,
        public readonly float $percent = 0.0,
        public readonly int $current_batch = 0,
        public readonly int $total_batches = 0,
    ) {}
}
