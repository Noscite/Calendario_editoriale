<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

/**
 * Generative Engine Optimization (GEO) Analyzer.
 *
 * Misura quanto un brand è ottimizzato per i motori AI (Perplexity, ChatGPT, Gemini):
 * - Visibilità in Perplexity (il brand è trovabile con info specifiche?)
 * - Schema.org strutturato per machine-reading
 * - llms.txt (file dedicato ai crawler AI)
 * - Sitemap e robots.txt (crawlability AI)
 * - Contenuto semantico ricco e citabile
 */
final class GeoAnalyzer
{
    public function analyze(
        array   $rawData,
        string  $brandName,
        ?string $perplexityContent,
    ): array {
        $schema   = $rawData['schema_org']  ?? [];
        $sitemap  = $rawData['sitemap']      ?? false;
        $robots   = $rawData['robots_txt']   ?? [];
        $llmsTxt  = $rawData['llms_txt']     ?? ['found' => false];
        $isJsHeavy = $rawData['is_js_heavy'] ?? false;

        $issues = [];
        $score  = 100;

        // ── 1. Visibilità AI (Perplexity) ─────────────────────────────────
        $perplexityScore = $this->scorePerplexityVisibility($brandName, $perplexityContent);

        if ($perplexityScore === 0) {
            $issues[] = [
                'severity' => 'critical',
                'area'     => 'GEO',
                'message'  => 'Brand non trovato da Perplexity AI: nessun contenuto specifico indicizzato.',
                'action'   => 'Creare contenuti long-form citabili (blog, case study, guide) e ottenere menzioni su siti autorevoli di settore.',
            ];
            $score -= 35;
        } elseif ($perplexityScore === 1) {
            $issues[] = [
                'severity' => 'warning',
                'area'     => 'GEO',
                'message'  => 'Perplexity restituisce contenuto generico: il brand non è riconoscibile come entità distinta nei risultati AI.',
                'action'   => 'Aumentare la presenza su directory di settore, ottenere backlink autorevoli e pubblicare contenuti originali citabili.',
            ];
            $score -= 20;
        }
        // $perplexityScore === 2 → brand trovato con info specifiche, nessuna penalità

        // ── 2. Schema.org (structured data per AI) ────────────────────────
        if (! ($schema['found'] ?? false)) {
            $schemaNote = $isJsHeavy ? ' (SPA: verificare con Google Rich Results Test — può essere iniettato via JS)' : '';
            $issues[] = [
                'severity' => $isJsHeavy ? 'warning' : 'critical',
                'area'     => 'GEO',
                'message'  => 'Nessun markup Schema.org rilevato.' . $schemaNote,
                'action'   => 'Implementare almeno Organization + WebSite schema. Per business locali aggiungere LocalBusiness con indirizzo, telefono, orari e categorie.',
            ];
            $score -= $isJsHeavy ? 15 : 25;
        } elseif (! ($schema['has_local_business'] ?? false)) {
            $types = implode(', ', $schema['types'] ?? []);
            $issues[] = [
                'severity' => 'info',
                'area'     => 'GEO',
                'message'  => "Schema.org presente ({$types}) ma manca LocalBusiness/Organization con dati strutturati completi.",
                'action'   => 'Aggiungere schema LocalBusiness con NAP (Name, Address, Phone), orari, e geo-coordinates per visibilità locale in AI.',
            ];
            $score -= 10;
        }

        // ── 3. llms.txt ───────────────────────────────────────────────────
        if (! ($llmsTxt['found'] ?? false)) {
            $issues[] = [
                'severity' => 'warning',
                'area'     => 'GEO',
                'message'  => 'File llms.txt assente: i crawler AI (ChatGPT, Perplexity, Gemini) non hanno istruzioni specifiche su come indicizzare il sito.',
                'action'   => 'Creare /llms.txt con descrizione del brand, servizi, contatti e pagine chiave in formato leggibile da AI.',
            ];
            $score -= 15;
        }

        // ── 4. Sitemap (crawlability AI) ──────────────────────────────────
        if (! $sitemap) {
            $issues[] = [
                'severity' => 'info',
                'area'     => 'GEO',
                'message'  => 'Sitemap XML assente: i crawler AI hanno difficoltà a scoprire tutte le pagine del sito.',
                'action'   => 'Generare sitemap.xml e registrarla su Google Search Console e Bing Webmaster Tools.',
            ];
            $score -= 10;
        }

        // ── 5. Robots.txt (AI bot policy) ─────────────────────────────────
        if (! ($robots['found'] ?? false)) {
            $issues[] = [
                'severity' => 'info',
                'area'     => 'GEO',
                'message'  => 'File robots.txt assente: nessuna direttiva per i crawler AI.',
                'action'   => 'Creare robots.txt con riferimento alla sitemap. Verificare di non bloccare GPTBot, PerplexityBot, ClaudeBot.',
            ];
            $score -= 5;
        } elseif ($robots['disallows_all'] ?? false) {
            $issues[] = [
                'severity' => 'critical',
                'area'     => 'GEO',
                'message'  => 'robots.txt blocca tutti i crawler (Disallow: /): il sito è invisibile a Google e ai motori AI.',
                'action'   => 'Rimuovere o limitare il blocco universale in robots.txt.',
            ];
            $score -= 20;
        }

        return [
            'score'              => max(0, $score),
            'perplexity_visible' => $perplexityScore,
            'has_schema'         => $schema['found'] ?? false,
            'schema_types'       => $schema['types'] ?? [],
            'has_local_business' => $schema['has_local_business'] ?? false,
            'has_llms_txt'       => $llmsTxt['found'] ?? false,
            'has_sitemap'        => $sitemap,
            'has_robots'         => $robots['found'] ?? false,
            'issues'             => $issues,
        ];
    }

    /**
     * Valuta la visibilità del brand in Perplexity.
     * 0 = non trovato / null
     * 1 = trovato ma contenuto generico (brand name non menzionato)
     * 2 = trovato con info specifiche sul brand
     */
    private function scorePerplexityVisibility(string $brandName, ?string $content): int
    {
        if (empty($content)) {
            return 0;
        }

        // Check if the content mentions the brand name specifically
        $brandLower   = mb_strtolower(trim($brandName));
        $contentLower = mb_strtolower($content);

        // Generic fallback phrases that mean Perplexity found nothing specific
        $genericPhrases = [
            'non ho trovato',
            'non sono riuscito',
            'non dispongo di informazioni',
            'non ho informazioni specifiche',
            'unable to find',
            'no specific information',
            'i could not find',
            'definizione',
            'in generale',
        ];

        foreach ($genericPhrases as $phrase) {
            if (str_contains($contentLower, $phrase)) {
                return 0;
            }
        }

        // Check if brand name appears in content
        if (! empty($brandLower) && str_contains($contentLower, $brandLower)) {
            return 2; // Brand specifically mentioned
        }

        return 1; // Content found but brand not specifically mentioned
    }
}
