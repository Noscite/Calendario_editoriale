<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Generation\Contracts\TrendResearcherInterface;

/**
 * Implementazione stub del researcher di trend.
 *
 * Verrà sostituita con PerplexityTrendResearcher quando Perplexity sarà configurato.
 */
final class StubTrendResearcher implements TrendResearcherInterface
{
    public function analyzeReferenceUrls(array $urls): array
    {
        return [];
    }

    public function research(array $params): array
    {
        return [];
    }

    public function analyzeBrandWebsite(string $url): array
    {
        return [];
    }
}
