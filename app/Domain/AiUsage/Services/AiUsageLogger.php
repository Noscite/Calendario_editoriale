<?php

declare(strict_types=1);

namespace App\Domain\AiUsage\Services;

use App\Domain\AiUsage\Data\UsageRecord;
use App\Domain\AiUsage\Models\AiUsageEvent;
use Illuminate\Support\Facades\Log;

/**
 * Persiste ogni chiamata AI in ai_usage_events, a prescindere dal fatto che
 * produca o meno un Post. Best-effort: un errore di logging non deve mai
 * far fallire la generazione di contenuti.
 */
class AiUsageLogger
{
    public function log(
        UsageRecord $record,
        ?int $organizationId,
        ?int $brandId = null,
        ?int $projectId = null,
    ): void {
        if ($organizationId === null) {
            // Nessun contesto org: probabilmente una chiamata di sistema
            // non riconducibile a un tenant. Non tracciabile in modo sensato.
            return;
        }

        try {
            AiUsageEvent::create([
                'organization_id'       => $organizationId,
                'brand_id'              => $brandId,
                'project_id'            => $projectId,
                'purpose'               => $record->purpose ?? 'unknown',
                'provider'              => $record->provider,
                'model'                 => $record->model,
                'input_tokens'          => $record->inputTokens,
                'output_tokens'         => $record->outputTokens,
                'cache_creation_tokens' => $record->cacheCreationTokens,
                'cache_read_tokens'     => $record->cacheReadTokens,
                'cost_usd'              => $record->costUsd,
                'cost_eur'              => $record->costEur,
                'created_at'            => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[AI USAGE] Log evento fallito', ['error' => $e->getMessage()]);
        }
    }
}
