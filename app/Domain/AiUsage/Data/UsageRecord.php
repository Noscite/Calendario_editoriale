<?php

declare(strict_types=1);

namespace App\Domain\AiUsage\Data;

/**
 * Snapshot del consumo di una singola chiamata AI.
 * Serializzabile in JSON per finire in Post::generation_metadata.usage.
 */
final class UsageRecord
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly int    $inputTokens = 0,
        public readonly int    $outputTokens = 0,
        public readonly int    $cacheCreationTokens = 0,
        public readonly int    $cacheReadTokens = 0,
        public readonly int    $imageCount = 0,
        public readonly ?string $imageSize = null,
        public readonly float  $costUsd = 0.0,
        public readonly float  $costEur = 0.0,
        public readonly ?string $purpose = null,
    ) {}

    public function toArray(): array
    {
        return [
            'provider'              => $this->provider,
            'model'                 => $this->model,
            'input_tokens'          => $this->inputTokens,
            'output_tokens'         => $this->outputTokens,
            'cache_creation_tokens' => $this->cacheCreationTokens,
            'cache_read_tokens'     => $this->cacheReadTokens,
            'image_count'           => $this->imageCount,
            'image_size'            => $this->imageSize,
            'cost_usd'              => round($this->costUsd, 6),
            'cost_eur'              => round($this->costEur, 6),
            'purpose'               => $this->purpose,
        ];
    }
}
