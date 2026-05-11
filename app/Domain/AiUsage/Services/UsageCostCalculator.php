<?php

declare(strict_types=1);

namespace App\Domain\AiUsage\Services;

use App\Domain\AiUsage\Data\UsageRecord;

/**
 * Calcola costo USD + EUR da response Anthropic/OpenAI.
 */
class UsageCostCalculator
{
    /**
     * @param  array<string, mixed>  $response  json decoded Anthropic API response
     */
    public function fromAnthropic(array $response, string $model, ?string $purpose = null): UsageRecord
    {
        $usage         = $response['usage'] ?? [];
        $inputTokens   = (int) ($usage['input_tokens'] ?? 0);
        $outputTokens  = (int) ($usage['output_tokens'] ?? 0);
        $cacheCreation = (int) ($usage['cache_creation_input_tokens'] ?? 0);
        $cacheRead     = (int) ($usage['cache_read_input_tokens'] ?? 0);

        $pricing = $this->anthropicPricing($model);

        $costUsd =
            ($inputTokens   / 1_000_000) * $pricing['input']
          + ($outputTokens  / 1_000_000) * $pricing['output']
          + ($cacheCreation / 1_000_000) * $pricing['cache_creation']
          + ($cacheRead     / 1_000_000) * $pricing['cache_read'];

        return new UsageRecord(
            provider:            'anthropic',
            model:               $model,
            inputTokens:         $inputTokens,
            outputTokens:        $outputTokens,
            cacheCreationTokens: $cacheCreation,
            cacheReadTokens:     $cacheRead,
            costUsd:             $costUsd,
            costEur:             $costUsd * $this->usdToEur(),
            purpose:             $purpose,
        );
    }

    public function fromImageGen(
        string $provider,
        string $model,
        string $size,
        int $count = 1,
        ?string $purpose = null,
    ): UsageRecord {
        $pricing = config("ai_pricing.{$provider}.{$model}", []);
        $perCall = $pricing[$size] ?? config("ai_pricing.{$provider}.default", 0.04);

        $costUsd = $perCall * $count;

        return new UsageRecord(
            provider:   $provider,
            model:      $model,
            imageCount: $count,
            imageSize:  $size,
            costUsd:    $costUsd,
            costEur:    $costUsd * $this->usdToEur(),
            purpose:    $purpose,
        );
    }

    private function anthropicPricing(string $model): array
    {
        return config("ai_pricing.anthropic.{$model}")
            ?? config('ai_pricing.anthropic.default');
    }

    private function usdToEur(): float
    {
        return (float) config('ai_pricing.usd_to_eur', 0.93);
    }
}
