<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Generation\Services\PerplexityTrendResearcher;
use Illuminate\Support\Facades\Log;

/**
 * Riusa PerplexityTrendResearcher per analisi semantica del sito e dei social.
 * Perplexity renderizza JS/SPA — funziona anche con React/Lovable/Next.js.
 */
final class PerplexityAuditBridge
{
    public function __construct(
        private readonly PerplexityTrendResearcher $researcher,
    ) {}

    /**
     * Analisi semantica del sito web (neuromarketing + brand voice).
     * Usa fetchUrlContent() già esistente.
     */
    public function analyzeWebsiteSemantic(string $websiteUrl, string $sector): array
    {
        try {
            $content = $this->researcher->fetchUrlContent($websiteUrl);

            if (empty($content)) {
                return ['error' => 'Impossibile analizzare il sito via Perplexity.', 'content' => null];
            }

            return ['content' => $content, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("[AUDIT-BRIDGE] Website semantic analysis failed: {$e->getMessage()}");
            return ['content' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analisi profilo social (coerenza brand voice, frequenza, contenuti).
     * Usa analyzeCompetitor() già esistente.
     */
    public function analyzeSocialProfile(string $socialUrl, string $platform): array
    {
        try {
            $content = $this->researcher->analyzeCompetitor($socialUrl);

            if (empty($content)) {
                return ['platform' => $platform, 'error' => 'Nessun dato disponibile.', 'content' => null];
            }

            return ['platform' => $platform, 'content' => $content, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("[AUDIT-BRIDGE] Social analysis failed for {$platform}: {$e->getMessage()}");
            return ['platform' => $platform, 'content' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analizza tutti i profili social del brand configurati.
     */
    public function analyzeAllSocials(array $socialUrls): array
    {
        $results = [];

        foreach ($socialUrls as $platform => $url) {
            if (! empty($url)) {
                $results[$platform] = $this->analyzeSocialProfile($url, $platform);
            }
        }

        return $results;
    }
}
