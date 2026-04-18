<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

final class SeoGeoAnalyzer
{
    /**
     * @param array      $rawData        Da WebsiteDirectFetcher (DOM statico)
     * @param array|null $playwrightData Da PlaywrightClient (DOM renderizzato)
     */
    public function analyze(array $rawData, ?array $playwrightData = null): array
    {
        // ── Arricchimento con dati Playwright ────────────────────────────────
        // Su siti SPA/React il DOM statico non ha H1, heading o link.
        // Se Playwright ha estratto il testo, usiamo quello per recuperare i dati.
        if ($playwrightData && ! empty($playwrightData['page_text'])) {
            $pageText = $playwrightData['page_text'];

            // Estrai H1 dal title se rawData non ce l'ha
            if (empty($rawData['headings']['h1'] ?? [])) {
                $title = $rawData['meta']['title'] ?? '';
                if ($title) {
                    $cleanTitle = preg_replace('/\s*[\|\-–]\s*.+$/', '', $title);
                    $cleanTitle = trim($cleanTitle);
                    if (! empty($cleanTitle)) {
                        $rawData['headings']['h1'] = [$cleanTitle];
                    }
                }
            }

            // Fallback: prima riga significativa del page_text
            if (empty($rawData['headings']['h1'] ?? [])) {
                $lines = array_filter(
                    array_map('trim', explode("\n", $pageText)),
                    fn($l) => strlen($l) > 10 && strlen($l) < 100
                );
                if (! empty($lines)) {
                    $rawData['headings']['h1'] = [array_values($lines)[0]];
                }
            }

            // Arricchisci H2 se vuoti (struttura base presente)
            if (empty($rawData['headings']['h2'] ?? [])) {
                $rawData['headings']['h2'] = ['(rilevato via rendering)'];
            }
        }

        // Axe-core come arbitro finale sulla struttura heading
        $axeViolations      = $playwrightData['axe']['violations'] ?? [];
        $hasAxeHeadingIssue = collect($axeViolations)
            ->contains(fn($v) => str_contains($v['id'] ?? '', 'heading'));

        $meta      = $rawData['meta']      ?? [];
        $headings  = $rawData['headings']  ?? [];
        $schema    = $rawData['schema_org'] ?? [];
        $robots    = $rawData['robots_txt'] ?? [];
        $sitemap   = $rawData['sitemap']   ?? false;
        $isJsHeavy = $rawData['is_js_heavy'] ?? false;

        $issues = [];
        $score  = 100;

        // ── Title ─────────────────────────────────────────────
        $title    = $meta['title'] ?? '';
        $titleLen = mb_strlen($title);

        if (empty($title)) {
            $issues[] = ['severity' => 'critical', 'area' => 'SEO',
                'message' => 'Tag <title> assente.',
                'action'  => 'Aggiungere un title tag descrittivo (50-60 caratteri).'];
            $score -= 25;
        } elseif ($titleLen < 30) {
            if ($isJsHeavy) {
                // SPA: static HTML title is a fallback; real per-page titles are set by React Helmet.
                $issues[] = ['severity' => 'info', 'area' => 'SEO',
                    'message' => "Title statico di {$titleLen} caratteri — probabile placeholder. Il sito è una SPA: verificare che ogni pagina imposti il proprio title via react-helmet-async.",
                    'action'  => 'Assicurarsi che tutte le route abbiano un title univoco e ottimale (50-60 caratteri) tramite react-helmet-async.'];
                $score -= 3;
            } else {
                $issues[] = ['severity' => 'critical', 'area' => 'SEO',
                    'message' => "Title tag di {$titleLen} caratteri — troppo corto (ottimale: 50-60). Non descrive il contenuto.",
                    'action'  => 'Riscrivere il title con keyword principale + nome brand (50-60 caratteri).'];
                $score -= 15;
            }
        } elseif ($titleLen > 65) {
            $issues[] = ['severity' => 'warning', 'area' => 'SEO',
                'message' => "Title tag di {$titleLen} caratteri — troppo lungo, verrà troncato da Google.",
                'action'  => 'Accorciare il title a massimo 60 caratteri.'];
            $score -= 5;
        }

        // ── Meta description ──────────────────────────────────
        $desc    = $meta['description'] ?? '';
        $descLen = mb_strlen($desc);

        if (empty($desc)) {
            if ($isJsHeavy) {
                // SPA: description is often managed per-page by React Helmet/Head components.
                // Missing from static HTML doesn't mean it's missing for real users/Google.
                $issues[] = ['severity' => 'warning', 'area' => 'SEO',
                    'message' => 'Meta description assente nell\'HTML statico. Il sito è una SPA: verificare che ogni pagina la imposti tramite React Helmet o simili.',
                    'action'  => 'Assicurarsi che tutte le route abbiano una meta description univoca (usare react-helmet-async o framework SSR/SSG).'];
                $score -= 8;
            } else {
                $issues[] = ['severity' => 'critical', 'area' => 'SEO',
                    'message' => 'Meta description completamente assente. Google genera snippet automatici spesso fuorvianti.',
                    'action'  => 'Aggiungere meta description di 150-160 caratteri con keyword principale e CTA chiara.'];
                $score -= 25;
            }
        } elseif ($descLen < 100) {
            if ($isJsHeavy) {
                $issues[] = ['severity' => 'info', 'area' => 'SEO',
                    'message' => "Meta description statica di {$descLen} caratteri — probabile placeholder. Il sito è una SPA: ogni pagina dovrebbe impostare la propria description via react-helmet-async.",
                    'action'  => 'Verificare che tutte le route abbiano una meta description univoca e ottimale (150-160 caratteri) tramite react-helmet-async.'];
                // No score penalty — per-page descriptions are set by JS
            } else {
                $issues[] = ['severity' => 'warning', 'area' => 'SEO',
                    'message' => "Meta description di {$descLen} caratteri — troppo corta (ottimale: 150-160).",
                    'action'  => 'Espandere la meta description includendo keyword secondarie e invito all\'azione.'];
                $score -= 8;
            }
        } elseif ($descLen > 165) {
            if ($isJsHeavy) {
                $issues[] = ['severity' => 'info', 'area' => 'SEO',
                    'message' => "Meta description statica di {$descLen} caratteri — probabile placeholder. Il sito è una SPA: ogni pagina dovrebbe impostare la propria description via react-helmet-async.",
                    'action'  => 'Verificare che tutte le route abbiano una meta description univoca e ottimale (150-160 caratteri) tramite react-helmet-async.'];
            } else {
                $issues[] = ['severity' => 'warning', 'area' => 'SEO',
                    'message' => "Meta description di {$descLen} caratteri — verrà troncata nelle SERP.",
                    'action'  => 'Accorciare a massimo 160 caratteri mantenendo le informazioni chiave.'];
                $score -= 5;
            }
        }

        // ── H1 ────────────────────────────────────────────────
        $h1s = $headings['h1'] ?? [];

        // Se Playwright ha analizzato la pagina e axe non ha trovato
        // problemi di heading, non penalizzare anche se il DOM statico era vuoto.
        $playwrightConfirmsOk = $playwrightData !== null
            && ($playwrightData['axe'] !== null)
            && ! $hasAxeHeadingIssue;

        if (empty($h1s) && ! $playwrightConfirmsOk) {
            if ($isJsHeavy) {
                // SPA: H1 is rendered by JavaScript. Google executes JS so it sees the H1.
                // Static scanners don't — this is a scanner limitation, not an SEO issue.
                $issues[] = ['severity' => 'info', 'area' => 'SEO',
                    'message' => 'H1 non rilevato nell\'HTML statico. Il sito è una SPA React/Vue: il contenuto è renderizzato da JavaScript, visibile a Google ma non agli scanner statici.',
                    'action'  => 'Nessuna azione necessaria se il framework gestisce correttamente il rendering. Per massima compatibilità con tutti i crawler, valutare SSR/SSG (Next.js, Nuxt.js).'];
                // No score penalty — Google handles JS rendering well
            } else {
                $issues[] = ['severity' => 'critical', 'area' => 'SEO', 'message' => 'Nessun tag H1 rilevato.', 'action' => 'Aggiungere un H1 principale con la keyword principale della pagina.'];
                $score -= 15;
            }
        } elseif (count($h1s) > 1) {
            $issues[] = ['severity' => 'warning', 'area' => 'SEO', 'message' => count($h1s) . ' H1 rilevati (dovrebbe essere unico).', 'action' => 'Mantenere un solo H1 per pagina.'];
            $score -= 8;
        }
        // Se $playwrightConfirmsOk → axe ha verificato la struttura, nessuna penalità

        // ── Open Graph ────────────────────────────────────────
        if (empty($meta['og_title']) && empty($meta['og_description']) && empty($meta['og_image'])) {
            $issues[] = ['severity' => 'critical', 'area' => 'SEO',
                'message' => 'Open Graph completamente assente. Condivisioni su Facebook/LinkedIn mostrano anteprima vuota.',
                'action'  => 'Aggiungere og:title, og:description, og:image per condivisioni social efficaci.'];
            $score -= $isJsHeavy ? 8 : 15;
        } elseif (empty($meta['og_title']) || empty($meta['og_description'])) {
            $ogSeverity = $isJsHeavy ? 'info' : 'warning';
            $ogNote     = $isJsHeavy ? ' (SPA: verificare che ogni pagina imposti i meta OG via Helmet/Head)' : '';
            $issues[] = ['severity' => $ogSeverity, 'area' => 'SEO',
                'message' => 'Open Graph incompleto (og:title o og:description mancanti).' . $ogNote,
                'action'  => 'Completare i meta tag Open Graph per migliorare la condivisione social.'];
            $score -= $isJsHeavy ? 4 : 8;
        }

        // ── Schema.org ────────────────────────────────────────
        if (! ($schema['found'] ?? false)) {
            $issues[] = ['severity' => 'critical', 'area' => 'GEO',
                'message' => 'Nessun markup Schema.org rilevato. Google non può creare rich snippet per questo sito.',
                'action'  => 'Implementare schema.org appropriato: LocalBusiness, Organization, Event, Product secondo i contenuti del sito.'];
            $score -= 20;
        } elseif (! ($schema['has_local_business'] ?? false)) {
            $issues[] = ['severity' => 'warning', 'area' => 'GEO',
                'message' => 'Schema.org presente ma senza LocalBusiness/Organization. Mancano dati strutturati per la ricerca locale.',
                'action'  => 'Aggiungere schema LocalBusiness con indirizzo, telefono, orari e coordinate GPS.'];
            $score -= 8;
        }

        // ── Sitemap ───────────────────────────────────────────
        if (! $sitemap) {
            $issues[] = ['severity' => 'warning', 'area' => 'SEO',
                'message' => 'Sitemap XML non trovata. Google non ha una mappa completa del sito.',
                'action'  => 'Generare e pubblicare sitemap.xml. Registrarla su Google Search Console.'];
            $score -= 10;
        }

        // ── Robots ────────────────────────────────────────────
        if (! ($robots['found'] ?? false)) {
            $issues[] = ['severity' => 'info', 'area' => 'SEO',
                'message' => 'File robots.txt non trovato.',
                'action'  => 'Creare robots.txt con riferimento alla sitemap.'];
            $score -= 5;
        }

        // ── Canonical ─────────────────────────────────────────
        if (empty($meta['canonical'])) {
            $issues[] = ['severity' => 'warning', 'area' => 'SEO',
                'message' => 'Tag canonical assente. Rischio contenuti duplicati penalizzati da Google.',
                'action'  => 'Aggiungere rel="canonical" su ogni pagina per evitare duplicate content.'];
            $score -= 8;
        }

        return [
            'score'         => max(0, $score),
            'title'         => ['text' => $title, 'length' => $titleLen],
            'description'   => ['text' => $desc, 'length' => $descLen],
            'h1_count'      => count($h1s),
            'h1_texts'      => $h1s,
            'has_og'        => ! empty($meta['og_title']),
            'has_sitemap'   => $sitemap,
            'has_robots'    => $robots['found'] ?? false,
            'has_canonical' => ! empty($meta['canonical']),
            'issues'        => $issues,
        ];
    }

    /**
     * Arricchisce il risultato SEO con dati PageSpeed e SSL.
     * Aggiunge penalità allo score SEO quando performance o SSL sono insufficienti
     * (entrambi influenzano direttamente il ranking Google).
     */
    public function enrichWithPerformance(array $seoResult, array $pageSpeedResult, array $sslResult): array
    {
        // Merge issues da tutte le fonti
        $allIssues = array_merge(
            $seoResult['issues']       ?? [],
            $pageSpeedResult['issues'] ?? [],
            $sslResult['issues']       ?? [],
        );

        $baseScore = $seoResult['score'];

        // PageSpeed mobile basso pesa sul SEO (Google lo usa per ranking)
        $mobileScore = $pageSpeedResult['mobile_score'] ?? null;
        if ($mobileScore !== null) {
            if ($mobileScore < 50)     $baseScore -= 15;
            elseif ($mobileScore < 75) $baseScore -= 7;
        }

        // SSL non configurato correttamente è penalità SEO diretta
        $sslScore = $sslResult['score'] ?? null;
        if ($sslScore !== null && $sslScore < 50) {
            $baseScore -= 10;
        }

        return array_merge($seoResult, [
            'score'     => max(0, min(100, $baseScore)),
            'pagespeed' => $pageSpeedResult,
            'ssl'       => $sslResult,
            'issues'    => $allIssues,
        ]);
    }
}
