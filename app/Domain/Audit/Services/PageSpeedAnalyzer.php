<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analizza performance e Core Web Vitals tramite Google PageSpeed Insights API.
 *
 * API gratuita (100 req/day senza chiave, 2000/day con chiave gratuita Google Cloud).
 * Endpoint: https://www.googleapis.com/pagespeedonline/v5/runPagespeed
 *
 * Ritorna score 0-100 e metriche Core Web Vitals per desktop e mobile.
 */
final class PageSpeedAnalyzer
{
    private const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    // Soglie Core Web Vitals — fonte: web.dev/vitals
    private const THRESHOLDS = [
        'lcp'  => ['good' => 2500, 'poor' => 4000],   // ms: Largest Contentful Paint
        'fid'  => ['good' => 100,  'poor' => 300],     // ms: First Input Delay
        'cls'  => ['good' => 0.1,  'poor' => 0.25],   // score: Cumulative Layout Shift
        'fcp'  => ['good' => 1800, 'poor' => 3000],   // ms: First Contentful Paint
        'ttfb' => ['good' => 800,  'poor' => 1800],   // ms: Time to First Byte
        'si'   => ['good' => 3400, 'poor' => 5800],   // ms: Speed Index
        'tbt'  => ['good' => 200,  'poor' => 600],    // ms: Total Blocking Time
    ];

    public function analyze(string $url): array
    {
        if (! config('services.pagespeed.enabled', true)) {
            return $this->emptyResult($url, 'PageSpeed disabled');
        }

        Log::info("[PAGESPEED] Analyzing: {$url}");

        [$desktop, $mobile] = $this->fetchBothStrategies($url);

        if (! $desktop && ! $mobile) {
            return $this->emptyResult($url, 'API non raggiungibile');
        }

        $desktopScore  = $desktop ? $this->extractScore($desktop) : null;
        $mobileScore   = $mobile  ? $this->extractScore($mobile)  : null;
        $desktopCwv    = $desktop ? $this->extractCoreWebVitals($desktop, 'desktop') : null;
        $mobileCwv     = $mobile  ? $this->extractCoreWebVitals($mobile, 'mobile')  : null;
        $opportunities = $desktop ? $this->extractOpportunities($desktop) : [];

        // Score audit = media pesata desktop/mobile (mobile conta di più per SEO)
        $auditScore = $this->calculateAuditScore($desktopScore, $mobileScore);
        $issues     = $this->buildIssues($desktopScore, $mobileScore, $desktopCwv, $mobileCwv);

        Log::info("[PAGESPEED] Complete — desktop: {$desktopScore}, mobile: {$mobileScore}");

        return [
            'score'           => $auditScore,
            'desktop_score'   => $desktopScore,
            'mobile_score'    => $mobileScore,
            'core_web_vitals' => [
                'desktop' => $desktopCwv,
                'mobile'  => $mobileCwv,
            ],
            'opportunities'   => $opportunities,
            'issues'          => $issues,
            'error'           => null,
        ];
    }

    // ── Fetch parallelo desktop + mobile ─────────────────────────────────
    private function fetchBothStrategies(string $url): array
    {
        $apiKey = config('services.pagespeed.api_key', '');

        $buildUrl = fn(string $strategy) => self::API_URL . '?' . http_build_query(array_filter([
            'url'      => $url,
            'strategy' => $strategy,
            'category' => 'performance',
            'key'      => $apiKey ?: null,
            'locale'   => 'it',
        ]));

        try {
            $responses = Http::pool(fn($pool) => [
                $pool->as('desktop')->timeout(90)->get($buildUrl('desktop')),
                $pool->as('mobile')->timeout(90)->get($buildUrl('mobile')),
            ]);

            $desktop = $responses['desktop']?->successful() ? $responses['desktop']->json() : null;
            $mobile  = $responses['mobile']?->successful()  ? $responses['mobile']->json()  : null;

            return [$desktop, $mobile];
        } catch (\Throwable $e) {
            Log::error("[PAGESPEED] Fetch error: {$e->getMessage()}");
            return [null, null];
        }
    }

    // ── Score Lighthouse (0-100) ──────────────────────────────────────────
    private function extractScore(array $data): ?int
    {
        $score = $data['lighthouseResult']['categories']['performance']['score'] ?? null;
        return $score !== null ? (int) round($score * 100) : null;
    }

    // ── Core Web Vitals ───────────────────────────────────────────────────
    private function extractCoreWebVitals(array $data, string $strategy): array
    {
        $audits = $data['lighthouseResult']['audits'] ?? [];

        $extract = fn(string $key) => [
            'value'   => $audits[$key]['numericValue'] ?? null,
            'display' => $audits[$key]['displayValue'] ?? null,
            'score'   => $audits[$key]['score'] ?? null,
        ];

        $lcp  = $extract('largest-contentful-paint');
        $fid  = $extract('max-potential-fid');
        $cls  = $extract('cumulative-layout-shift');
        $fcp  = $extract('first-contentful-paint');
        $si   = $extract('speed-index');
        $tbt  = $extract('total-blocking-time');
        $ttfb = $extract('server-response-time');

        return [
            'strategy'            => $strategy,
            'lcp'                 => $lcp,
            'fid'                 => $fid,
            'cls'                 => $cls,
            'fcp'                 => $fcp,
            'speed_index'         => $si,
            'total_blocking_time' => $tbt,
            'ttfb'                => $ttfb,
            'rating'              => $this->rateCwv($lcp, $cls, $tbt),
        ];
    }

    private function rateCwv(array $lcp, array $cls, array $tbt): string
    {
        $lcpMs  = $lcp['value'] ?? PHP_INT_MAX;
        $clsVal = $cls['value'] ?? PHP_INT_MAX;
        $tbtMs  = $tbt['value'] ?? PHP_INT_MAX;

        $good = $lcpMs  <= self::THRESHOLDS['lcp']['good']
             && $clsVal <= self::THRESHOLDS['cls']['good']
             && $tbtMs  <= self::THRESHOLDS['tbt']['good'];

        $poor = $lcpMs  >= self::THRESHOLDS['lcp']['poor']
             || $clsVal >= self::THRESHOLDS['cls']['poor']
             || $tbtMs  >= self::THRESHOLDS['tbt']['poor'];

        if ($good) return 'good';
        if ($poor) return 'poor';
        return 'needs_improvement';
    }

    // ── Opportunità di miglioramento ──────────────────────────────────────
    private function extractOpportunities(array $data): array
    {
        $audits = $data['lighthouseResult']['audits'] ?? [];

        return collect($audits)
            ->filter(fn($a) => ($a['score'] ?? 1) < 0.9
                && isset($a['details']['type'])
                && $a['details']['type'] === 'opportunity'
                && isset($a['numericValue'])
                && $a['numericValue'] > 0)
            ->sortBy(fn($a) => -($a['numericValue'] ?? 0))
            ->take(6)
            ->map(fn($a, $key) => [
                'id'          => $key,
                'title'       => $a['title'] ?? '',
                'description' => $a['description'] ?? '',
                'display'     => $a['displayValue'] ?? '',
                'savings_ms'  => (int) ($a['numericValue'] ?? 0),
            ])
            ->values()
            ->toArray();
    }

    // ── Issues per il report ──────────────────────────────────────────────
    private function buildIssues(?int $desktopScore, ?int $mobileScore, ?array $desktopCwv, ?array $mobileCwv): array
    {
        $issues = [];

        if ($mobileScore !== null && $mobileScore < 50) {
            $issues[] = [
                'severity' => 'critical',
                'area'     => 'Performance',
                'message'  => "Score performance mobile: {$mobileScore}/100. Il sito è lento su smartphone — Google penalizza siti lenti nel ranking.",
                'action'   => 'Ottimizzare immagini, ridurre JavaScript bloccante, abilitare caching. Vedere le opportunità specifiche nel report.',
            ];
        } elseif ($mobileScore !== null && $mobileScore < 75) {
            $issues[] = [
                'severity' => 'warning',
                'area'     => 'Performance',
                'message'  => "Score performance mobile: {$mobileScore}/100. Margini di miglioramento significativi.",
                'action'   => 'Ottimizzare le opportunità evidenziate per migliorare il ranking su Google.',
            ];
        }

        if ($mobileCwv && $mobileCwv['rating'] === 'poor') {
            $lcpDisplay = $mobileCwv['lcp']['display'] ?? 'N/D';
            $issues[] = [
                'severity' => 'critical',
                'area'     => 'Core Web Vitals',
                'message'  => "Core Web Vitals mobile insufficienti. LCP: {$lcpDisplay}. Google penalizza direttamente questi siti nei risultati di ricerca.",
                'action'   => 'Priorità assoluta: ottimizzare LCP (immagine/testo principale above-fold) e ridurre il layout shift.',
            ];
        }

        return $issues;
    }

    // ── Score audit (0-100) ───────────────────────────────────────────────
    private function calculateAuditScore(?int $desktop, ?int $mobile): int
    {
        if ($desktop === null && $mobile === null) return 50;
        if ($desktop === null) return $mobile;
        if ($mobile === null)  return $desktop;
        // Mobile pesa 60%, desktop 40%
        return (int) round($mobile * 0.6 + $desktop * 0.4);
    }

    private function emptyResult(string $url, string $reason): array
    {
        return [
            'score' => null, 'desktop_score' => null, 'mobile_score' => null,
            'core_web_vitals' => ['desktop' => null, 'mobile' => null],
            'opportunities'   => [], 'issues' => [],
            'error'           => $reason,
        ];
    }
}
