<?php

declare(strict_types=1);

namespace App\Domain\Audit\Jobs;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Models\BrandAudit;
use App\Domain\Audit\Services\AccessibilityAnalyzer;
use App\Domain\Audit\Services\AuditScorerService;
use App\Domain\Audit\Services\GdprAnalyzer;
use App\Domain\Audit\Services\GeoAnalyzer;
use App\Domain\Audit\Services\NeuromarketingVisionAnalyzer;
use App\Domain\Audit\Services\PageSpeedAnalyzer;
use App\Domain\Audit\Services\PlaywrightClient;
use App\Domain\Audit\Services\SeoGeoAnalyzer;
use App\Domain\Audit\Services\SslAnalyzer;
use App\Domain\Audit\Services\WebsiteDirectFetcher;
use App\Domain\Brand\Models\Brand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class RunBrandAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300; // 5 minuti

    public function __construct(
        private readonly ?int $brandId,
        private readonly int  $auditId,
    ) {}

    public function handle(
        WebsiteDirectFetcher         $fetcher,
        GdprAnalyzer                 $gdpr,
        SeoGeoAnalyzer               $seo,
        GeoAnalyzer                  $geo,
        AccessibilityAnalyzer        $a11y,
        AuditScorerService           $scorer,
        PlaywrightClient             $playwright,
        PageSpeedAnalyzer            $pageSpeed,
        SslAnalyzer                  $ssl,
        NeuromarketingVisionAnalyzer $neuroVision,
    ): void {
        $audit = BrandAudit::findOrFail($this->auditId);
        $brand = $this->brandId ? Brand::find($this->brandId) : null;

        // Prospect/standalone audit: usa i dati direttamente dall'audit record
        // standalone_data ha priorità su prospect_name/sector (campi legacy)
        $brandName  = $brand?->name
            ?? $audit->standalone_data['company_name']
            ?? $audit->prospect_name
            ?? 'Sito web';
        $sector     = $brand?->sector
            ?? $audit->standalone_data['sector']
            ?? $audit->prospect_sector
            ?? '';
        $websiteUrl = $brand?->website_url ?? $brand?->website        ?? $audit->website_url ?? null;

        Log::info("[AUDIT] Starting audit #{$this->auditId} for '{$brandName}'");

        $audit->update(['status' => AuditStatus::Running]);

        try {

            // ── 1a. Fetch diretto (meta, script, DOM statico) ─────────
            $rawData = $websiteUrl
                ? $fetcher->fetch($websiteUrl)
                : [];

            // ── 1b. Playwright: analisi profonda (se disponibile) ──────
            $playwrightData = null;
            if ($websiteUrl) {
                Log::info("[AUDIT] Starting Playwright analysis for {$websiteUrl}");
                $playwrightData = $playwright->analyze($websiteUrl);

                if ($playwrightData) {
                    Log::info("[AUDIT] Playwright analysis complete — axe violations: "
                        . count($playwrightData['axe']['violations'] ?? []));
                } else {
                    Log::info("[AUDIT] Playwright not available, using static analysis only.");
                }
            }

            // ── 1c. PageSpeed + SSL (paralleli in background) ─────────
            $pageSpeedResult = $websiteUrl ? $pageSpeed->analyze($websiteUrl) : [];
            $sslResult       = $websiteUrl ? $ssl->analyze($websiteUrl)       : [];

            // ── 2. Analyzer tecnici ───────────────────────────────────
            $gdprResult = $gdpr->analyze($rawData, $playwrightData);
            $seoResult  = $seo->analyze($rawData, $playwrightData);
            $seoResult  = $seo->enrichWithPerformance($seoResult, $pageSpeedResult, $sslResult);
            $a11yResult = $a11y->analyze($rawData, $playwrightData);

            // ── 3. Analisi semantica: testo reale da Playwright (no Perplexity) ──
            if ($playwrightData && ! empty($playwrightData['page_text'])) {
                $pageText = $playwrightData['page_text'];
                Log::info("[AUDIT] Page text extracted: " . strlen($pageText) . " chars");
            } else {
                // Fallback: testo dal DOM statico (meta + headings)
                $meta     = $rawData['meta'] ?? [];
                $headings = $rawData['headings'] ?? [];
                $pageText = implode(' ', array_filter([
                    $meta['title']       ?? '',
                    $meta['description'] ?? '',
                    implode(' ', $headings['h1'] ?? []),
                    implode(' ', $headings['h2'] ?? []),
                    implode(' ', array_slice($headings['h3'] ?? [], 0, 3)),
                ]));
                Log::info("[AUDIT] Using static fallback text: " . strlen($pageText) . " chars");
            }

            // ── 3b. GEO: Generative Engine Optimization ───────────────────────
            $geoResult = $geo->analyze($rawData, $brandName, $pageText);
            Log::info("[AUDIT] GEO score: {$geoResult['score']}");

            // ── 3c. Neuromarketing Vision: analisi visiva con Claude Vision ───
            $neuroVisionResult = [];
            if ($playwrightData && (
                ! empty($playwrightData['screenshots']['desktop']) ||
                ! empty($playwrightData['screenshots']['mobile'])
            )) {
                Log::info("[AUDIT] Starting NeuroVision analysis for '{$brandName}'");

                $brandProfile = [
                    'tone_of_voice'   => $brand?->tone_of_voice,
                    'brand_values'    => $brand?->brand_values,
                    'target_audience' => $brand?->target_audience,
                    'colors'          => $brand?->colors,
                ];

                $neuroVisionResult = $neuroVision->analyze(
                    sector:            $sector,
                    brandName:         $brandName,
                    screenshotDesktop: $playwrightData['screenshots']['desktop'] ?? null,
                    screenshotMobile:  $playwrightData['screenshots']['mobile']  ?? null,
                    brandProfile:      $brandProfile,
                );

                Log::info("[AUDIT] NeuroVision score: " . ($neuroVisionResult['score'] ?? 'N/D'));
            } else {
                Log::info("[AUDIT] NeuroVision skipped — no screenshots available.");
            }

            // ── 4. Claude: executive summary + neuromarketing ────────────
            // Usa sector_key confermato dall'utente, altrimenti lo deduce dal nome settore
            $sectorKey = $audit->standalone_data['sector_key']
                ?? $this->detectSectorKey($sector);

            $claudeResult = $scorer->scoreAndSummarize(
                sector:              $sector,
                brandName:           $brandName,
                rawData:             $rawData,
                seoResult:           $seoResult,
                gdprResult:          $gdprResult,
                accessibilityResult: $a11yResult,
                pageText:            $pageText,
                playwrightData:      $playwrightData,
                pageSpeedResult:     $pageSpeedResult,
                sslResult:           $sslResult,
                neuroVisionResult:   $neuroVisionResult,
                sectorKey:           $sectorKey,
            );

            // ── 5. Score overall (pesi dinamici per settore) ──────────────
            // Vision analyzer ha priorità sul score neuromarketing generico di Claude
            $scoreNeuromarketing  = $neuroVisionResult['score']
                ?? ($claudeResult['score_neuromarketing'] ?? 50);
            $scoreSeo             = $seoResult['score'];
            $scoreGeo             = $geoResult['score'];
            $scoreGdpr            = $gdprResult['score'];
            $scoreA11y            = $a11yResult['score'];
            $scoreSocialCoherence = 50; // neutro — social coherence rimossa dal pipeline
            $scorePerformance     = ! empty($pageSpeedResult['score']) ? $pageSpeedResult['score'] : null;

            // Pesi dinamici per settore
            $weights = $this->getSectorWeights($sectorKey);

            if ($scorePerformance !== null) {
                $scoreOverall = (int) round(
                    ($scoreNeuromarketing  * $weights['neuromarketing']) +
                    ($scoreSeo             * $weights['seo'])            +
                    ($scoreGdpr            * $weights['gdpr'])           +
                    ($scorePerformance     * $weights['performance'])    +
                    ($scoreSocialCoherence * $weights['social'])         +
                    ($scoreA11y            * $weights['accessibility'])
                );
            } else {
                // Performance mancante → ridistribuisci il suo peso
                // MA penalizza leggermente (non sapere = rischio)
                $totalWithoutPerf = 1.0 - $weights['performance'];
                $perfPenalty      = 5;

                $scoreOverall = (int) round(
                    (
                        ($scoreNeuromarketing  * $weights['neuromarketing'] / $totalWithoutPerf) +
                        ($scoreSeo             * $weights['seo']            / $totalWithoutPerf) +
                        ($scoreGdpr            * $weights['gdpr']           / $totalWithoutPerf) +
                        ($scoreSocialCoherence * $weights['social']         / $totalWithoutPerf) +
                        ($scoreA11y            * $weights['accessibility']  / $totalWithoutPerf)
                    ) - $perfPenalty
                );

                $scoreOverall = max(0, $scoreOverall);
            }

            Log::info("[AUDIT] Sector weights applied: {$sectorKey} — overall: {$scoreOverall}");

            // ── 6. Salvataggio ────────────────────────────────────────
            // Gli screenshot NON vengono salvati nel DB (troppo pesanti —
            // vengono usati solo per la chiamata Claude in memoria).
            $playwrightMeta = $playwrightData ? [
                'analyzed'    => true,
                'network'     => $playwrightData['network'],
                'axe_summary' => $playwrightData['axe'] ? [
                    'violations' => count($playwrightData['axe']['violations'] ?? []),
                    'passes'     => $playwrightData['axe']['passes'] ?? 0,
                ] : null,
            ] : ['analyzed' => false];

            $audit->update([
                'status'                 => AuditStatus::Completed,
                'score_overall'          => $scoreOverall,
                'score_neuromarketing'   => $scoreNeuromarketing,
                'score_seo_geo'          => $scoreSeo,
                'score_geo'              => $scoreGeo,
                'score_social_coherence' => $scoreSocialCoherence,
                'score_gdpr'             => $scoreGdpr,
                'score_accessibility'    => $scoreA11y,
                'score_performance'      => $scorePerformance,
                'raw_data'               => array_merge($rawData, ['playwright' => $playwrightMeta]),
                'analyzer_results'       => [
                    'gdpr'           => $gdprResult,
                    'seo_geo'        => $seoResult,
                    'geo'            => $geoResult,
                    'accessibility'  => $a11yResult,
                    'neuromarketing' => array_merge(
                        $claudeResult['neuromarketing_details'] ?? [],
                        ['vision' => $neuroVisionResult],
                    ),
                    'pagespeed'      => $pageSpeedResult,
                    'ssl'            => $sslResult,
                    'scoring_meta'   => [
                        'sector_key'            => $sectorKey,
                        'weights_used'          => $weights,
                        'performance_available' => $scorePerformance !== null,
                    ],
                ],
                'executive_summary' => $claudeResult['executive_summary'] ?? '',
                'critical_issues'   => $this->normalizeCriticalIssues($claudeResult['critical_issues'] ?? []),
                'recommendations'   => $this->normalizeRecommendations($claudeResult['recommendations'] ?? []),
                'completed_at'      => now(),
            ]);

            Log::info("[AUDIT] Completed audit #{$this->auditId} — overall score: {$scoreOverall}");

        } catch (\Throwable $e) {
            Log::error("[AUDIT] Failed audit #{$this->auditId}: {$e->getMessage()}");

            $audit->update([
                'status'        => AuditStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pesi score per settore.
     * Riflettono l'importanza relativa di ogni categoria per il business specifico.
     * I pesi devono sommare a 1.0.
     *
     * @return array{neuromarketing:float, seo:float, gdpr:float, performance:float, social:float, accessibility:float}
     */
    private function getSectorWeights(?string $sectorKey): array
    {
        return match ($sectorKey) {

            // Siti turistici/territoriali: SEO è tutto (traffico organico locale)
            'turismo', 'alimentare' => [
                'neuromarketing' => 0.20,
                'seo'            => 0.40,
                'gdpr'           => 0.15,
                'performance'    => 0.15,
                'social'         => 0.05,
                'accessibility'  => 0.05,
            ],

            // Professionisti sanitari: GDPR e neuromarketing critici
            'psicologia', 'medico', 'farmacia' => [
                'neuromarketing' => 0.30,
                'seo'            => 0.25,
                'gdpr'           => 0.30,
                'performance'    => 0.05,
                'social'         => 0.05,
                'accessibility'  => 0.05,
            ],

            // Legale/Finanza: credibilità e GDPR sopra tutto
            'legale', 'finanza' => [
                'neuromarketing' => 0.25,
                'seo'            => 0.25,
                'gdpr'           => 0.30,
                'performance'    => 0.10,
                'social'         => 0.05,
                'accessibility'  => 0.05,
            ],

            // E-commerce: performance e SEO critici per conversioni
            'commercio' => [
                'neuromarketing' => 0.20,
                'seo'            => 0.30,
                'gdpr'           => 0.15,
                'performance'    => 0.25,
                'social'         => 0.05,
                'accessibility'  => 0.05,
            ],

            // Tech/Agenzia: tutto bilanciato con focus performance
            'tech', 'consulenza' => [
                'neuromarketing' => 0.20,
                'seo'            => 0.25,
                'gdpr'           => 0.20,
                'performance'    => 0.20,
                'social'         => 0.05,
                'accessibility'  => 0.10,
            ],

            // Fitness/Estetica: social e neuromarketing importanti
            'fitness', 'estetica' => [
                'neuromarketing' => 0.30,
                'seo'            => 0.25,
                'gdpr'           => 0.15,
                'performance'    => 0.10,
                'social'         => 0.15,
                'accessibility'  => 0.05,
            ],

            // Default bilanciato
            default => [
                'neuromarketing' => 0.25,
                'seo'            => 0.25,
                'gdpr'           => 0.20,
                'performance'    => 0.15,
                'social'         => 0.05,
                'accessibility'  => 0.10,
            ],
        };
    }

    /**
     * Mappa il nome settore alla chiave deontologica per SectorDetectorService.
     */
    private function detectSectorKey(string $sector): string
    {
        $sectorLower = strtolower($sector);
        $mappings = [
            'psicolog'    => 'psicologia',
            'psicoterape' => 'psicologia',
            'counseling'  => 'psicologia',
            'medic'       => 'medico',
            'clinic'      => 'medico',
            'chirurg'     => 'medico',
            'avvocat'     => 'legale',
            'legale'      => 'legale',
            'notai'       => 'legale',
            'farmaci'     => 'farmacia',
            'finanz'      => 'finanza',
            'assicuraz'   => 'finanza',
            'ristoran'    => 'alimentare',
            'pizzeria'    => 'alimentare',
            'bar '        => 'alimentare',
            'catering'    => 'alimentare',
            'palestra'    => 'fitness',
            'trainer'     => 'fitness',
            'immobil'     => 'immobiliare',
            'hotel'       => 'turismo',
            'albergo'     => 'turismo',
        ];

        foreach ($mappings as $keyword => $key) {
            if (str_contains($sectorLower, $keyword)) {
                return $key;
            }
        }

        return 'altro';
    }

    /** Normalize critical_issues to [{area, message, impact}] regardless of Claude's output format. */
    private function normalizeCriticalIssues(array $issues): array
    {
        return array_values(array_filter(array_map(function ($issue) {
            if (is_string($issue)) {
                return ['area' => 'Generale', 'message' => $issue, 'impact' => null];
            }
            if (is_array($issue)) {
                return [
                    'area'    => $issue['area'] ?? ($issue['category'] ?? 'Generale'),
                    'message' => $issue['message'] ?? ($issue['description'] ?? ($issue['issue'] ?? '')),
                    'impact'  => $issue['impact'] ?? ($issue['business_impact'] ?? null),
                ];
            }
            return null;
        }, $issues)));
    }

    /** Normalize recommendations to [{priority, area, action, effort, impact}] regardless of Claude's output format. */
    private function normalizeRecommendations(array $recs): array
    {
        $result = [];
        $i      = 1;
        foreach ($recs as $rec) {
            if (is_string($rec)) {
                $result[] = ['priority' => $i++, 'area' => 'Generale', 'action' => $rec, 'effort' => null, 'impact' => null];
                continue;
            }
            if (! is_array($rec)) { continue; }

            // Claude sometimes returns nested "actions" array instead of single "action"
            $action = $rec['action'] ?? null;
            if (! $action && ! empty($rec['actions']) && is_array($rec['actions'])) {
                $action = implode(' ', array_map(fn ($a) => is_string($a) ? $a : ($a['action'] ?? ''), $rec['actions']));
            }
            $action = $action ?: ($rec['description'] ?? '');

            // Normalize effort/impact — Claude sometimes uses "ALTA"/"MEDIA" instead of "alto"/"basso"
            $effort = strtolower($rec['effort'] ?? $rec['priority'] ?? '');
            $effort = match (true) {
                str_contains($effort, 'alt') || str_contains($effort, 'high') => 'alto',
                str_contains($effort, 'med')                                   => 'medio',
                str_contains($effort, 'basso') || str_contains($effort, 'low') => 'basso',
                default                                                         => $effort ?: null,
            };

            $impact = strtolower($rec['impact'] ?? $rec['expected_impact'] ?? '');
            $impact = $impact ? mb_substr($impact, 0, 80) : null;

            $result[] = [
                'priority' => is_numeric($rec['priority'] ?? null) ? (int) $rec['priority'] : $i,
                'area'     => $rec['area'] ?? ($rec['category'] ?? 'Generale'),
                'action'   => $action,
                'effort'   => $effort,
                'impact'   => $impact,
            ];
            $i++;
        }
        return $result;
    }
}
