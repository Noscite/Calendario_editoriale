<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chiama Claude Sonnet per:
 * 1. Score neuromarketing contestualizzato per settore
 * 2. Score coerenza social
 * 3. Executive summary narrativo con priorità d'azione
 *
 * Quando Playwright fornisce screenshot, usa Claude Vision per valutare
 * il neuromarketing visivamente (above-fold desktop + mobile).
 */
final class AuditScorerService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL   = 'claude-sonnet-4-5';

    public function scoreAndSummarize(
        string  $sector,
        string  $brandName,
        array   $rawData,
        array   $seoResult,
        array   $gdprResult,
        array   $accessibilityResult,
        ?string $pageText           = null,
        ?array  $playwrightData     = null,
        array   $pageSpeedResult    = [],
        array   $sslResult          = [],
        array   $neuroVisionResult  = [],
        ?string $sectorKey          = null,
    ): array {
        $apiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));

        if (! $apiKey) {
            Log::warning('[AUDIT-SCORER] Anthropic API key not configured.');
            return $this->fallbackScoring($seoResult, $gdprResult, $accessibilityResult);
        }

        // Timeout più lungo con screenshot (payload base64 grande)
        $hasScreenshots = ! empty($playwrightData['screenshots']['desktop']);
        $requestTimeout = $hasScreenshots ? 180 : 120;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
                ->timeout($requestTimeout)
                ->post(self::API_URL, [
                    'model'      => self::MODEL,
                    'max_tokens' => 3500,
                    'system'     => 'Sei un consulente senior di digital marketing specializzato in PMI italiane. Rispondi SOLO con JSON valido, senza markdown.',
                    'messages'   => $this->buildMessages(
                        $sector, $brandName, $rawData,
                        $seoResult, $gdprResult, $accessibilityResult,
                        $pageText,
                        $pageSpeedResult, $sslResult,
                        $neuroVisionResult,
                        $sectorKey,
                    ),
                ]);

            if (! $response->successful()) {
                Log::error('[AUDIT-SCORER] Claude API error: ' . $response->status());
                return $this->fallbackScoring($seoResult, $gdprResult, $accessibilityResult);
            }

            $content = $response->json('content.0.text', '');

            // Strip markdown code fences if Claude wraps JSON in ```json ... ```
            $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
            $content = preg_replace('/```\s*$/m', '', $content);
            $content = trim($content);

            // Fix literal control chars (newlines/tabs) inside JSON string values
            // Claude sometimes writes multi-line strings; php json_decode rejects them
            $content = preg_replace_callback(
                '/"(?:[^"\\\\]|\\\\.)*"/s',
                fn ($m) => str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], $m[0]),
                $content
            );

            $parsed  = json_decode($content, true);

            if (! $parsed) {
                Log::warning('[AUDIT-SCORER] JSON parse failed. Raw: ' . substr($content, 0, 300));
                return $this->fallbackScoring($seoResult, $gdprResult, $accessibilityResult);
            }

            return $parsed;

        } catch (\Throwable $e) {
            Log::error("[AUDIT-SCORER] Error: {$e->getMessage()}");
            return $this->fallbackScoring($seoResult, $gdprResult, $accessibilityResult);
        }
    }

    /**
     * Costruisce i messages per Claude — con o senza screenshot.
     * Con screenshot: Claude Vision valuta il neuromarketing visivamente.
     * Senza screenshot: Claude valuta solo testo (come prima).
     */
    private function buildMessages(
        string  $sector,
        string  $brandName,
        array   $rawData,
        array   $seoResult,
        array   $gdprResult,
        array   $accessibilityResult,
        ?string $pageText,
        array   $pageSpeedResult,
        array   $sslResult,
        array   $neuroVisionResult,
        ?string $sectorKey = null,
    ): array {
        $prompt = $this->buildPrompt(
            $sector, $brandName, $rawData,
            $seoResult, $gdprResult, $accessibilityResult,
            $pageText,
            $pageSpeedResult, $sslResult,
            $neuroVisionResult,
            $sectorKey,
        );

        // Testo semplice: il neuromarketing visivo è già gestito da NeuromarketingVisionAnalyzer
        return [['role' => 'user', 'content' => $prompt]];
    }

    private function buildPrompt(
        string  $sector,
        string  $brandName,
        array   $rawData,
        array   $seoResult,
        array   $gdprResult,
        array   $accessibilityResult,
        ?string $pageText           = null,
        array   $pageSpeedResult    = [],
        array   $sslResult          = [],
        array   $neuroVisionResult  = [],
        ?string $sectorKey          = null,
    ): string {
        $isJsHeavy = $rawData['is_js_heavy'] ?? false;

        // Only pass [critical] and [warning] issues to Claude.
        // [info] items are scanner limitations for SPA sites — Claude must never see them or it will
        // hallucinate critical business problems from them regardless of any instruction.
        $filterIssues = fn(array $issues) => array_values(array_filter(
            $issues,
            fn($i) => in_array($i['severity'] ?? '', ['critical', 'warning'])
        ));

        $seoIssuesForClaude  = $filterIssues($seoResult['issues']  ?? []);
        $gdprIssuesForClaude = $filterIssues($gdprResult['issues'] ?? []);

        // For SPA: don't expose raw boolean fields that Claude misinterprets.
        // has_banner=false and h1_count=0 in a SPA mean "not found in static HTML", NOT "missing".
        if ($isJsHeavy) {
            $websiteInfo = json_encode([
                'title'        => $rawData['meta']['title'] ?? '',
                'is_spa'       => true,
                'has_og_image' => ! empty($rawData['meta']['og_image']),
                'note'         => 'SPA React/Vue. Title/description/H1 in static HTML are placeholders — real values are injected per-page by react-helmet-async. Do NOT flag these as SEO issues.',
            ], JSON_UNESCAPED_UNICODE);

            $seoSummary = json_encode([
                'score'         => $seoResult['score'],
                'has_canonical' => $seoResult['has_canonical'] ?? false,
                'has_sitemap'   => $seoResult['has_sitemap']   ?? false,
                'issues'        => $seoIssuesForClaude,
                'note'          => 'SPA: title/description length and H1 are scanner artifacts, NOT real SEO problems.',
            ], JSON_UNESCAPED_UNICODE);

            $gdprSummary = json_encode([
                'score'            => $gdprResult['score'],
                'trackers_in_html' => $gdprResult['trackers_in_html'] ?? [],
                'issues'           => $gdprIssuesForClaude,
                'note'             => 'SPA: cookie banner and privacy links are rendered by JS — static scanner cannot detect them. Do NOT flag their absence as GDPR violations.',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $websiteInfo = json_encode([
                'title'        => $rawData['meta']['title'] ?? '',
                'description'  => $rawData['meta']['description'] ?? '',
                'is_spa'       => false,
                'h1'           => $rawData['headings']['h1'] ?? [],
                'h2'           => array_slice($rawData['headings']['h2'] ?? [], 0, 5),
                'has_og_image' => ! empty($rawData['meta']['og_image']),
            ], JSON_UNESCAPED_UNICODE);

            $seoSummary = json_encode([
                'score'         => $seoResult['score'],
                'h1_count'      => $seoResult['h1_count'] ?? 0,
                'has_canonical' => $seoResult['has_canonical'] ?? false,
                'has_sitemap'   => $seoResult['has_sitemap']   ?? false,
                'issues'        => $seoIssuesForClaude,
            ], JSON_UNESCAPED_UNICODE);

            $gdprSummary = json_encode([
                'score'            => $gdprResult['score'],
                'has_banner'       => $gdprResult['has_cookie_banner'],
                'trackers_in_html' => $gdprResult['trackers_in_html'] ?? [],
                'issues'           => $gdprIssuesForClaude,
            ], JSON_UNESCAPED_UNICODE);
        }

        $a11ySummary = json_encode(['score' => $accessibilityResult['score'], 'issues_count' => count($accessibilityResult['issues'])], JSON_UNESCAPED_UNICODE);

        $semanticSnippet = $pageText
            ? mb_substr($pageText, 0, 2000)
            : 'Testo non disponibile.';

        $pageSpeedInfo = ! empty($pageSpeedResult) ? json_encode([
            'desktop_score'     => $pageSpeedResult['desktop_score'] ?? null,
            'mobile_score'      => $pageSpeedResult['mobile_score'] ?? null,
            'cwv_mobile_rating' => $pageSpeedResult['core_web_vitals']['mobile']['rating'] ?? null,
            'lcp_mobile'        => $pageSpeedResult['core_web_vitals']['mobile']['lcp']['display'] ?? null,
            'cls_mobile'        => $pageSpeedResult['core_web_vitals']['mobile']['cls']['display'] ?? null,
        ], JSON_UNESCAPED_UNICODE) : 'Non disponibile';

        $sslInfo = ! empty($sslResult) ? json_encode([
            'rating'    => $sslResult['rating'] ?? null,
            'cert_days' => $sslResult['cert_days_left'] ?? null,
            'has_https' => $sslResult['has_https'] ?? null,
        ], JSON_UNESCAPED_UNICODE) : 'Non disponibile';

        // Neuromarketing visivo già calcolato da NeuromarketingVisionAnalyzer
        $neuroScore   = $neuroVisionResult['score'] ?? null;
        $neuroVerdict = $neuroVisionResult['above_fold_verdict'] ?? null;
        $neuroMissing = ! empty($neuroVisionResult['sector_elements_missing'])
            ? implode(', ', $neuroVisionResult['sector_elements_missing'])
            : 'N/D';

        $neuroContext = ($neuroScore !== null && empty($neuroVisionResult['error']))
            ? "Score: {$neuroScore}/100 — {$neuroVerdict}\nElementi mancanti: {$neuroMissing}"
            : 'Analisi visiva non disponibile.';

        $spaCaution = $isJsHeavy
            ? "\nIMPORTANTE: Questo è un sito SPA React/Vue. I dati tecnici (title, description, H1, cookie banner, privacy link) provengono dall'HTML statico che è solo una shell vuota — tutti i contenuti reali sono renderizzati da JavaScript. NON menzionare mai assenza di H1, meta description corta, assenza cookie banner o assenza privacy link come problemi: sono artefatti dello scanner, non problemi reali. Basa l'analisi SOLO sulle issues fornite nei dati JSON.\n"
            : '';

        $deontologicalBlock = $this->buildDeontologicalBlock($sectorKey);

        return <<<PROMPT
Analizza il sito web di questo brand e produci un audit digitale completo.
{$spaCaution}
BRAND: {$brandName}
SETTORE: {$sector}

DATI TECNICI SITO:
{$websiteInfo}

TESTO REALE DELLA PAGINA (estratto dopo rendering JavaScript):
{$semanticSnippet}

SCORE TECNICI GIÀ CALCOLATI:
- SEO/GEO: {$seoSummary}
- GDPR: {$gdprSummary}
- Accessibilità: {$a11ySummary}

PERFORMANCE (Google PageSpeed Insights):
{$pageSpeedInfo}

SICUREZZA (SSL Labs):
{$sslInfo}

VINCOLI DEONTOLOGICI E SETTORIALI:
{$deontologicalBlock}

NOTA: L'analisi di coerenza social non è disponibile in questa versione.
Assegna score_social_coherence = 50 come valore neutro.

NOTA: Il score neuromarketing visivo è già stato calcolato separatamente tramite analisi delle immagini della pagina. Non includere score_neuromarketing nel JSON — ti viene fornito come contesto per l'executive summary.

NEUROMARKETING VISIVO (già calcolato):
{$neuroContext}

Il tuo compito:
1. Assegna score_social_coherence = 50 (valore neutro fisso — analisi social non disponibile).
2. Scrivi executive_summary (300-400 parole): tono diretto e professionale, spiega i problemi principali e il loro impatto sul business. NON essere generico — cita elementi specifici del testo della pagina e integra il contesto del neuromarketing visivo. Rispetta i vincoli deontologici del settore. Concludi con le 3 azioni prioritarie più impattanti.
3. Elenca critical_issues: massimo 5 problemi critici, ordinati per impatto business.
4. Elenca recommendations: massimo 8 raccomandazioni pratiche con stima effort (basso/medio/alto). Le raccomandazioni DEVONO rispettare i vincoli deontologici indicati.

Rispondi SOLO con questo JSON:
{
  "score_social_coherence": 50,
  "executive_summary": "...",
  "critical_issues": [
    {"area": "GDPR", "message": "...", "impact": "..."}
  ],
  "recommendations": [
    {"priority": 1, "area": "SEO", "action": "...", "effort": "basso", "impact": "alto"}
  ],
  "neuromarketing_details": {
    "strengths": ["..."],
    "weaknesses": ["..."],
    "sector_specific_notes": "..."
  }
}
PROMPT;
    }

    private function buildDeontologicalBlock(?string $sectorKey): string
    {
        if (! $sectorKey || $sectorKey === 'altro') {
            return 'Nessun vincolo deontologico specifico. Applica best practice standard.';
        }

        $constraints = \App\Domain\Audit\Services\SectorDetectorService::DEONTOLOGICAL_CONSTRAINTS[$sectorKey] ?? null;

        if (! $constraints) {
            return 'Nessun vincolo deontologico specifico per questo settore.';
        }

        $forbidden = implode("\n- ", $constraints['forbidden']);
        $preferred = implode("\n- ", $constraints['preferred']);

        return <<<BLOCK
Settore: {$sectorKey} — REGOLAMENTATO

VIETATO nelle raccomandazioni (vincoli deontologici/legali italiani):
- {$forbidden}

PREFERITO per questo settore:
- {$preferred}

IMPORTANTE: Le raccomandazioni devono rispettare questi vincoli.
Una raccomandazione che viola la deontologia professionale è peggio
di non darla — danneggia la credibilità del professionista.
BLOCK;
    }

    private function fallbackScoring(array $seo, array $gdpr, array $a11y): array
    {
        return [
            'score_social_coherence' => 50,
            'executive_summary'      => 'Analisi AI non disponibile. Consultare i dettagli per categoria.',
            'critical_issues'        => [],
            'recommendations'        => [],
            'neuromarketing_details' => ['strengths' => [], 'weaknesses' => [], 'sector_specific_notes' => ''],
        ];
    }
}
