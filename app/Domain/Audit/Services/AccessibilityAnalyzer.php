<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

final class AccessibilityAnalyzer
{
    /**
     * Analisi accessibilità: DOM statico + axe-core WCAG 2.1 da Playwright.
     *
     * @param array      $rawData        Dati da WebsiteDirectFetcher
     * @param array|null $playwrightData Dati da PlaywrightClient (null se non disponibile)
     */
    public function analyze(array $rawData, ?array $playwrightData = null): array
    {
        // ── Override con dati axe-core (fonte più autorevole) ────────────────
        // axe-core gira sul DOM renderizzato da Playwright: è più accurato del
        // DOM statico per siti SPA. Se axe non segnala un problema, non lo segnaliamo.
        if ($playwrightData && ! empty($playwrightData['axe'])) {
            $axeViolations = $playwrightData['axe']['violations'] ?? [];

            // Se axe NON ha trovato problemi di lang → lang è ok
            $axeHasLangIssue = collect($axeViolations)
                ->contains(fn($v) => in_array($v['id'] ?? '', [
                    'html-has-lang', 'html-lang-valid', 'html-xml-lang-mismatch',
                ]));

            if (! $axeHasLangIssue && empty($rawData['lang'] ?? null)) {
                // Il lang probabilmente c'è nel DOM renderizzato ma il fetch statico
                // non l'ha catturato; axe lo conferma ok
                $rawData['lang'] = 'it';
            }

            // Se axe NON ha trovato problemi di immagini alt → non penalizzare
            $axeHasAltIssue = collect($axeViolations)
                ->contains(fn($v) => $v['id'] === 'image-alt');

            if (! $axeHasAltIssue && ($rawData['images']['without_alt'] ?? 0) > 0) {
                // Le immagini senza alt nel DOM statico erano decorative o placeholder
                $rawData['images']['without_alt']          = 0;
                $rawData['images']['without_alt_examples'] = [];
            }

            // Se axe NON ha trovato problemi di heading → non penalizzare per H1 mancante
            $axeHasHeadingIssue = collect($axeViolations)
                ->contains(fn($v) => str_contains($v['id'] ?? '', 'heading'));

            if (! $axeHasHeadingIssue && empty($rawData['headings']['h1'] ?? [])) {
                // H1 c'è nel DOM renderizzato; lo scanner statico non lo vede
                $rawData['headings']['h1'] = ['(rilevato via rendering)'];
            }
        }

        $images   = $rawData['images']   ?? [];
        $headings = $rawData['headings'] ?? [];
        $lang     = $rawData['lang']     ?? null;
        $meta     = $rawData['meta']     ?? [];

        $issues = [];
        $score  = 100;

        // ── Analisi base (DOM statico) ─────────────────────────────────
        if (! $lang) {
            $issues[] = [
                'severity' => 'critical',
                'message'  => 'Attributo lang assente nel tag <html>.',
                'action'   => 'Aggiungere lang="it" (o lingua appropriata) all\'elemento html.',
                'wcag'     => 'WCAG 3.1.1',
            ];
            $score -= 20;
        }

        $totalImages = $images['total']       ?? 0;
        $withoutAlt  = $images['without_alt'] ?? 0;

        if ($totalImages > 0 && $withoutAlt > 0) {
            $pct      = round($withoutAlt / $totalImages * 100);
            $severity = $pct > 50 ? 'critical' : 'warning';
            $issues[] = [
                'severity' => $severity,
                'message'  => "{$withoutAlt} immagini su {$totalImages} ({$pct}%) prive di attributo alt.",
                'action'   => 'Aggiungere alt text descrittivo a tutte le immagini non decorative. Le immagini decorative usino alt="".',
                'wcag'     => 'WCAG 1.1.1',
                'examples' => $images['without_alt_examples'] ?? [],
            ];
            $score -= min(25, (int) ($pct / 2));
        }

        $h1s = $headings['h1'] ?? [];
        $h2s = $headings['h2'] ?? [];

        if (empty($h1s)) {
            $issues[] = [
                'severity' => 'critical',
                'message'  => 'Nessun H1: la struttura della pagina non è accessibile ai lettori di schermo.',
                'action'   => 'Definire un H1 principale.',
                'wcag'     => 'WCAG 1.3.1',
            ];
            $score -= 15;
        }

        if (empty($meta['viewport'])) {
            $issues[] = [
                'severity' => 'critical',
                'message'  => 'Meta viewport assente: il sito non è ottimizzato per mobile.',
                'action'   => 'Aggiungere <meta name="viewport" content="width=device-width, initial-scale=1">.',
                'wcag'     => 'WCAG 1.4.4',
            ];
            $score -= 15;
        }

        // ── Arricchimento axe-core (Playwright) ───────────────────────
        $axeViolations   = [];
        $axeSummary      = null;
        $playwrightDepth = 'static';

        if ($playwrightData !== null && ! empty($playwrightData['axe'])) {
            $playwrightDepth = 'wcag_2_1';
            $axe             = $playwrightData['axe'];
            $axeViolations   = $axe['violations'] ?? [];
            $axeSummary      = [
                'violations_count' => count($axeViolations),
                'passes_count'     => $axe['passes'] ?? 0,
                'incomplete_count' => $axe['incomplete'] ?? 0,
            ];

            // Penalità aggiuntive dalle violation axe non già coperte dallo static
            $alreadyPenalized = 0;

            foreach ($axeViolations as $violation) {
                $impact  = $violation['impact'];
                $penalty = match($impact) {
                    'critical' => 12,
                    'serious'  => 8,
                    'moderate' => 4,
                    'minor'    => 1,
                    default    => 2,
                };

                // Non penalizzare due volte per alt e lang (già coperti sopra)
                if (in_array($violation['id'], ['image-alt', 'html-has-lang', 'html-lang-valid'])) {
                    continue;
                }

                if ($alreadyPenalized < 30) {
                    $score -= $penalty;
                    $alreadyPenalized += $penalty;
                }

                $issues[] = [
                    'severity'          => in_array($impact, ['critical', 'serious']) ? 'critical' : 'warning',
                    'message'           => $violation['help'],
                    'action'            => "Vedi: {$violation['helpUrl']}",
                    'wcag'              => implode(', ', array_slice($violation['wcag'] ?? [], 0, 2)),
                    'source'            => 'axe-core',
                    'impact'            => $impact,
                    'affected_elements' => $violation['nodes_count'] ?? 0,
                    'examples'          => $violation['examples'] ?? [],
                ];
            }
        }

        return [
            'score'          => max(0, (int) $score),
            'analysis_depth' => $playwrightDepth,
            'lang'           => $lang,
            'images_total'   => $totalImages,
            'images_no_alt'  => $withoutAlt,
            'h1_count'       => count($h1s),
            'h2_count'       => count($h2s),
            'has_viewport'   => ! empty($meta['viewport']),
            'axe_summary'    => $axeSummary,
            'axe_violations' => $axeViolations,
            'issues'         => $issues,
        ];
    }
}
