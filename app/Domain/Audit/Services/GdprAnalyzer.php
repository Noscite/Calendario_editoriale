<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

/**
 * Analizza conformità GDPR dalla struttura DOM raccolta da WebsiteDirectFetcher,
 * arricchita opzionalmente con i dati di intercettazione network di Playwright.
 */
final class GdprAnalyzer
{
    /**
     * Analisi GDPR combinata: DOM statico + intercettazione network Playwright.
     *
     * @param array      $rawData        Dati da WebsiteDirectFetcher (DOM statico)
     * @param array|null $playwrightData Dati da PlaywrightClient (null se non disponibile)
     */
    public function analyze(array $rawData, ?array $playwrightData = null): array
    {
        // ── Arricchimento con page_text da Playwright ─────────────────────────
        // Su siti SPA il DOM statico non ha link nel footer (caricati da JS).
        // Cerchiamo privacy policy e cookie policy nel testo renderizzato.
        if ($playwrightData && (! empty($playwrightData['page_text']) || ! empty($playwrightData['footer_links_text']))) {
            // Combina page_text e footer_links_text: il footer è escluso dal page_text
            // ma contiene tipicamente i link a Privacy Policy e Cookie Policy
            $pageText = strtolower(
                ($playwrightData['page_text']         ?? '') . ' ' .
                ($playwrightData['footer_links_text'] ?? '')
            );

            if (! ($rawData['links']['privacy_policy'] ?? false)) {
                $hasPrivacyInText = str_contains($pageText, 'privacy policy')
                    || str_contains($pageText, 'informativa privacy')
                    || str_contains($pageText, 'privacy e cookie')
                    || str_contains($pageText, 'politica privacy')
                    || str_contains($pageText, 'informativa sulla privacy');

                if ($hasPrivacyInText) {
                    $rawData['links']['privacy_policy'] = true;
                }
            }

            if (! ($rawData['links']['cookie_policy'] ?? false)) {
                $hasCookieInText = str_contains($pageText, 'cookie policy')
                    || str_contains($pageText, 'informativa cookie')
                    || str_contains($pageText, 'gestione cookie')
                    || str_contains($pageText, 'preferenze cookie');

                if ($hasCookieInText) {
                    $rawData['links']['cookie_policy'] = true;
                }
            }
        }

        // Se Playwright vede il banner visivamente, arricchisci i dati statici
        if ($playwrightData) {
            $network = $playwrightData['network'] ?? [];
            if ($network['cookie_banner_visible'] ?? false) {
                $rawData['scripts']['cookie_banners'] = array_unique(array_merge(
                    $rawData['scripts']['cookie_banners'] ?? [],
                    $network['cmp_found'] ?? []
                ));
                if (empty($rawData['scripts']['cookie_banners'])) {
                    $rawData['scripts']['cookie_banners'] = ['(rilevato visivamente)'];
                }
            }
        }

        // ── Analisi base dal DOM statico ───────────────────────────────
        $scripts  = $rawData['scripts']  ?? [];
        $links    = $rawData['links']    ?? [];
        $isJsHeavy = $rawData['is_js_heavy'] ?? false;

        $hasCookieBannerStatic = ! empty($scripts['cookie_banners']);
        $hasPrivacyPolicy      = $links['privacy_policy'] ?? false;
        $hasCookiePolicy       = $links['cookie_policy']  ?? false;
        $staticBanners         = $scripts['cookie_banners'] ?? [];

        // Distinguish: trackers hardcoded in <script src> vs only referenced in CSP allowlist.
        // CSP presence means "the site is allowed to load this" — not "it loads without consent".
        $trackersInHtml = $scripts['trackers_in_html'] ?? [];   // definite loads
        $trackersAllSrc = $scripts['trackers']         ?? [];   // includes CSP-only mentions

        // ── Arricchimento con dati Playwright ─────────────────────────
        $playwrightEnriched    = false;
        $trackersBeforeConsent = [];
        $cmpFound              = [];
        $cookieBannerVisible   = false;

        if ($playwrightData !== null) {
            $playwrightEnriched    = true;
            $network               = $playwrightData['network'] ?? [];
            $trackersBeforeConsent = $network['trackers_before_consent'] ?? [];
            $cmpFound              = $network['cmp_found'] ?? [];
            $cookieBannerVisible   = $network['cookie_banner_visible'] ?? false;
        }

        // For JS-heavy SPAs: consent can be implemented entirely in the JavaScript bundle.
        // If any consent-related pattern was found in the HTML source, treat it as present.
        $hasConsentInSource = $rawData['consent_detected'] ?? false;

        $hasCookieBanner = $hasCookieBannerStatic || ! empty($cmpFound) || $cookieBannerVisible
            || ($isJsHeavy && $hasConsentInSource);
        $allTrackers     = array_unique(array_merge($trackersAllSrc, $trackersBeforeConsent));

        // ── Scoring ───────────────────────────────────────────────────
        $issues = [];
        $score  = 100;

        if ($playwrightEnriched) {
            // ── CON PLAYWRIGHT: dati network reali, massima accuratezza ──
            if (! empty($trackersBeforeConsent) && ! $hasCookieBanner) {
                $issues[] = [
                    'severity' => 'critical',
                    'message'  => 'Tracker attivi prima del consenso: ' . implode(', ', $trackersBeforeConsent) . '. Nessun cookie banner rilevato.',
                    'action'   => 'Implementare un CMP (Iubenda, Cookiebot, OneTrust) che blocchi i tracker fino al consenso esplicito.',
                ];
                $score -= 45;
            } elseif (! empty($trackersBeforeConsent) && $hasCookieBanner) {
                $issues[] = [
                    'severity' => 'critical',
                    'message'  => 'I tracker (' . implode(', ', $trackersBeforeConsent) . ') si attivano prima dell\'interazione col banner. Violazione GDPR.',
                    'action'   => 'Configurare il CMP in modalità blocking: bloccare i tracker fino al consenso esplicito.',
                ];
                $score -= 30;
            } elseif (empty($trackersBeforeConsent) && ! $hasCookieBanner && ! empty($allTrackers)) {
                $issues[] = [
                    'severity' => 'warning',
                    'message'  => 'Tracker presenti ma non attivati prima del consenso. Verificare il comportamento post-interazione.',
                    'action'   => 'Aggiungere un CMP visibile per garantire la conformità.',
                ];
                $score -= 10;
            }

        } elseif ($isJsHeavy) {
            // ── SITO JS-HEAVY / SPA senza Playwright ──────────────────
            // L'analisi statica è strutturalmente limitata:
            // - Il cookie banner è spesso renderizzato da React/Vue (non visibile nel HTML statico)
            // - I tracker possono essere caricati condizionalmente dal JS dopo il consenso
            // - La CSP è un allowlist, non prova che i tracker si attivino senza consenso
            //
            // Penalizziamo SOLO se ci sono tracker hardcoded in <script src> senza banner.
            // Tracker visti solo nella CSP non sono una violazione certa.

            if (! empty($trackersInHtml) && ! $hasCookieBanner) {
                // Tracker effettivamente hardcoded nell'HTML + nessun banner rilevabile
                $issues[] = [
                    'severity' => 'warning',
                    'message'  => 'Tracker trovati nell\'HTML (' . implode(', ', $trackersInHtml) . '). Il cookie banner non è rilevabile da analisi statica (sito SPA).',
                    'action'   => 'Verificare che i tracker siano bloccati prima del consenso. Considerare l\'uso di un CMP certificato (es. Iubenda, Cookiebot) per maggiore certezza di conformità.',
                ];
                $score -= 15; // warning, non critical — non sappiamo se è condizionale
            } elseif (! empty($trackersAllSrc) && ! $hasCookieBanner) {
                // Tracker solo nella CSP (non in script src diretto) = molto probabilmente condizionale
                $issues[] = [
                    'severity' => 'info',
                    'message'  => 'Servizi di terze parti (' . implode(', ', $trackersAllSrc) . ') autorizzati nella CSP. In un\'app React/Vue questi sono tipicamente caricati solo dopo il consenso.',
                    'action'   => 'Verifica eseguibile solo con rendering JavaScript attivo. Assicurarsi che il consenso sia richiesto prima del caricamento dei tracker.',
                ];
                $score -= 5;
            } elseif (! $hasCookieBanner && empty($trackersAllSrc)) {
                $issues[] = [
                    'severity' => 'info',
                    'message'  => 'Cookie banner non rilevato da analisi statica. I siti SPA/React spesso mostrano il banner via JavaScript.',
                    'action'   => 'Verificare manualmente che il banner appaia al primo accesso in modalità incognito.',
                ];
                $score -= 5;
            }

        } else {
            // ── SITO STATICO / SERVER-RENDERED senza Playwright ───────
            if (! empty($trackersInHtml) && ! $hasCookieBanner) {
                $issues[] = [
                    'severity' => 'critical',
                    'message'  => 'Tracker hardcoded nell\'HTML (' . implode(', ', $trackersInHtml) . ') senza cookie banner rilevato.',
                    'action'   => 'Implementare un CMP prima di caricare tracker di terze parti.',
                ];
                $score -= 40;
            } elseif (! empty($trackersAllSrc) && ! $hasCookieBanner) {
                $issues[] = [
                    'severity' => 'critical',
                    'message'  => 'Tracker presenti (' . implode(', ', $trackersAllSrc) . ') senza cookie banner rilevato.',
                    'action'   => 'Implementare un CMP prima di caricare tracker di terze parti.',
                ];
                $score -= 35;
            } elseif (! $hasCookieBanner && empty($trackersAllSrc)) {
                $issues[] = [
                    'severity' => 'warning',
                    'message'  => 'Nessun cookie banner rilevato. Verificare se il sito usa cookie non tecnici.',
                    'action'   => 'Verificare presenza cookie e implementare CMP se necessario.',
                ];
                $score -= 10;
            }
        }

        if (! $hasPrivacyPolicy) {
            if ($isJsHeavy) {
                // SPA: privacy policy link is likely rendered by JS — can't verify statically
                $issues[] = [
                    'severity' => 'warning',
                    'message'  => 'Link a Privacy Policy non rilevato nell\'HTML statico. Il sito è una SPA: il link potrebbe essere renderizzato via JavaScript.',
                    'action'   => 'Verificare manualmente che il footer contenga un link visibile a Privacy Policy in ogni pagina.',
                ];
                $score -= 8;
            } else {
                $issues[] = [
                    'severity' => 'critical',
                    'message'  => 'Nessun link a Privacy Policy rilevato.',
                    'action'   => 'Aggiungere link visibile a Privacy Policy nel footer.',
                ];
                $score -= 25;
            }
        }

        if (! $hasCookiePolicy && ! empty($trackersInHtml)) {
            $issues[] = [
                'severity' => 'warning',
                'message'  => 'Nessuna Cookie Policy specifica rilevata.',
                'action'   => 'Aggiungere Cookie Policy distinta dalla Privacy Policy.',
            ];
            $score -= 10;
        }

        return [
            'score'                    => max(0, $score),
            'analysis_depth'           => $playwrightEnriched ? 'deep' : ($isJsHeavy ? 'spa_static' : 'static'),
            'has_cookie_banner'        => $hasCookieBanner,
            'cookie_banner_visible'    => $cookieBannerVisible,
            'cookie_banner_provider'   => $cmpFound[0] ?? ($staticBanners[0] ?? null),
            'has_privacy_policy'       => $hasPrivacyPolicy,
            'has_cookie_policy'        => $hasCookiePolicy,
            'trackers_found'           => $allTrackers,
            'trackers_in_html'         => $trackersInHtml,
            'trackers_before_consent'  => $trackersBeforeConsent,
            'trackers_without_consent' => ! empty($trackersBeforeConsent) && ! $hasCookieBanner,
            'issues'                   => $issues,
        ];
    }
}
