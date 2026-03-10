<?php

declare(strict_types=1);

namespace App\Domain\Generation\Contracts;

interface TrendResearcherInterface
{
    /**
     * Analizza URL di riferimento e restituisce contesto di brand.
     *
     * @param  array<string>  $urls  URL da analizzare.
     * @return array Contesto estratto dagli URL.
     */
    public function analyzeReferenceUrls(array $urls): array;

    /**
     * Ricerca trend e contesto per un brand/settore.
     *
     * @param  array{
     *     sector?: string,
     *     target_audience?: string,
     *     brand_name?: string,
     *     competitors?: string,
     *     platforms?: array<string>,
     * }  $params
     * @return array Trend e insight trovati.
     */
    public function research(array $params): array;

    /**
     * Analizza il sito web di un brand per estrarre contesto.
     */
    public function analyzeBrandWebsite(string $url): array;
}
