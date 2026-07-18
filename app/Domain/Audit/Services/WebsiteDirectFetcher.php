<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Esegue un fetch HTTP diretto del sito web.
 * NON renderizza JavaScript — raccoglie dati tecnici statici:
 * meta tags, script tags, heading structure, alt attributes, lang, link tags.
 */
final class WebsiteDirectFetcher
{
    private const TIMEOUT    = 15;
    private const USER_AGENT = 'Mozilla/5.0 (compatible; KalendariumAuditBot/1.0)';

    public function fetch(string $url): array
    {
        $url = $this->normalizeUrl($url);

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(self::TIMEOUT)
                ->get($url);

            if (! $response->successful()) {
                return $this->emptyResult($url, "HTTP {$response->status()}");
            }

            $html    = $response->body();
            // Content-Security-Policy: check both HTTP header and <meta http-equiv> tag
            // SPAs often set CSP via meta tag rather than HTTP header
            $cspHeader = $response->header('Content-Security-Policy') ?? '';
            if (! $cspHeader) {
                // Extract the full <meta http-equiv="Content-Security-Policy" ...> tag first,
                // then pull the content attribute from it (avoids single-quote-in-value issue)
                if (preg_match('/<meta[^>]+Content-Security-Policy[^>]*>/si', $html, $cspTagM)) {
                    if (preg_match('/content="([^"]+)"/si', $cspTagM[0], $cspContentM)) {
                        $cspHeader = $cspContentM[1];
                    }
                }
            }

            return [
                'url'         => $url,
                'status_code' => $response->status(),
                'html_length' => strlen($html),
                'is_js_heavy' => $this->detectJsHeavy($html),
                'meta'        => $this->extractMeta($html),
                'headings'    => $this->extractHeadings($html),
                'images'      => $this->extractImages($html),
                'scripts'          => $this->extractScripts($html, $cspHeader),
                'links'            => $this->extractLinks($html, $url),
                'social_links'     => $this->extractSocialLinks($html),
                'consent_detected' => $this->detectConsentInSource($html),
                'lang'        => $this->extractLang($html),
                'schema_org'  => $this->extractSchemaOrg($html),
                'robots_txt'  => $this->fetchRobotsTxt($url),
                'sitemap'     => $this->detectSitemap($url),
                'llms_txt'    => $this->detectLlmsTxt($url),
                'error'       => null,
            ];

        } catch (\Throwable $e) {
            Log::warning("[AUDIT] WebsiteDirectFetcher error for {$url}: {$e->getMessage()}");
            return $this->emptyResult($url, $e->getMessage());
        }
    }

    private function detectJsHeavy(string $html): bool
    {
        $bodyText    = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $bodyText    = strip_tags($bodyText ?? '');
        $wordCount   = str_word_count($bodyText);
        $scriptCount = substr_count($html, '<script');
        return $wordCount < 200 && $scriptCount > 3;
    }

    private function extractMeta(string $html): array
    {
        $meta = [
            'title'          => '',
            'description'    => '',
            'keywords'       => '',
            'og_title'       => '',
            'og_description' => '',
            'og_image'       => '',
            'og_type'        => '',
            'twitter_card'   => '',
            'canonical'      => '',
            'robots'         => '',
            'viewport'       => '',
            'charset'        => '',
        ];

        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            $meta['title'] = trim(html_entity_decode(strip_tags($m[1])));
        }

        preg_match_all('/<meta\s+([^>]+)>/si', $html, $matches);
        foreach ($matches[1] ?? [] as $attrs) {
            $name    = $this->attr($attrs, 'name') ?: $this->attr($attrs, 'property');
            $content = $this->attr($attrs, 'content') ?? '';
            $charset = $this->attr($attrs, 'charset');

            if ($charset) {
                $meta['charset'] = $charset;
            }

            match (strtolower((string) $name)) {
                'description'    => $meta['description']    = $content,
                'keywords'       => $meta['keywords']       = $content,
                'robots'         => $meta['robots']         = $content,
                'viewport'       => $meta['viewport']       = $content,
                'og:title'       => $meta['og_title']       = $content,
                'og:description' => $meta['og_description'] = $content,
                'og:image'       => $meta['og_image']       = $content,
                'og:type'        => $meta['og_type']        = $content,
                'twitter:card'   => $meta['twitter_card']   = $content,
                default          => null,
            };
        }

        if (preg_match('/<link[^>]+rel=["\x27]canonical["\x27][^>]+href=["\x27]([^"\x27]+)["\x27][^>]*>/si', $html, $m)) {
            $meta['canonical'] = $m[1];
        }

        return $meta;
    }

    private function extractHeadings(string $html): array
    {
        $headings = ['h1' => [], 'h2' => [], 'h3' => []];

        foreach (['h1', 'h2', 'h3'] as $tag) {
            preg_match_all("/<{$tag}[^>]*>(.*?)<\/{$tag}>/si", $html, $m);
            $headings[$tag] = array_map(
                fn ($t) => trim(html_entity_decode(strip_tags($t))),
                $m[1] ?? []
            );
        }

        return $headings;
    }

    private function extractImages(string $html): array
    {
        preg_match_all('/<img\s+([^>]+)>/si', $html, $matches);

        $result = ['total' => 0, 'without_alt' => 0, 'without_alt_examples' => []];

        foreach ($matches[1] ?? [] as $attrs) {
            $result['total']++;
            $alt = $this->attr($attrs, 'alt');
            $src = $this->attr($attrs, 'src') ?? '';

            if ($alt === null || $alt === '') {
                $result['without_alt']++;
                if (count($result['without_alt_examples']) < 5) {
                    $result['without_alt_examples'][] = $src;
                }
            }
        }

        return $result;
    }

    private function extractScripts(string $html, string $cspHeader = ''): array
    {
        $knownTrackers = [
            'google-analytics.com' => 'Google Analytics',
            'googletagmanager.com' => 'Google Tag Manager',
            'facebook.net'         => 'Meta Pixel',
            'connect.facebook.net' => 'Meta Pixel',
            'hotjar.com'           => 'Hotjar',
            'clarity.ms'           => 'Microsoft Clarity',
            'linkedin.com/insight' => 'LinkedIn Insight',
            'snap.licdn.com'       => 'LinkedIn Insight',
            'hubspot.com'          => 'HubSpot',
            'intercom.io'          => 'Intercom',
            'crisp.chat'           => 'Crisp',
        ];

        // Known CMP (cookie consent) providers — detected via script src, CSP, inline code, or HTML attributes
        $knownCmps = [
            'cookiebot.com'           => 'Cookiebot',
            'iubenda.com'             => 'Iubenda',
            'cookie-script.com'       => 'Cookie Script',
            'onetrust.com'            => 'OneTrust',
            'axept.io'                => 'Axeptio',
            'axeptio.eu'              => 'Axeptio',
            'cookiehub.com'           => 'CookieHub',
            'consentmanager.net'      => 'ConsentManager',
            'cookieinformation.com'   => 'CookieInformation',
            'usercentrics.eu'         => 'Usercentrics',
            'termly.io'               => 'Termly',
            'didomi.io'               => 'Didomi',
            'trustarc.com'            => 'TrustArc',
            'tarteaucitron.js'        => 'TarteAuCitron',
            'klaro.kiprotect.com'     => 'Klaro',
            'cookieconsent.insites.com' => 'Cookie Consent',
        ];

        // Inline JS patterns that indicate CMP presence even without an external script tag
        $inlineCmpPatterns = [
            '_iub'                => 'Iubenda',
            'iubenda_cs'          => 'Iubenda',
            'CookieConsent'       => 'Cookiebot',
            'Cookiebot'           => 'Cookiebot',
            'OptanonWrapper'      => 'OneTrust',
            'OneTrust'            => 'OneTrust',
            'axeptio'             => 'Axeptio',
            'Axeptio'             => 'Axeptio',
            'tarteaucitron'       => 'TarteAuCitron',
            'cookiehub'           => 'CookieHub',
            'consentmanager'      => 'ConsentManager',
            'didomi'              => 'Didomi',
            'klaro'               => 'Klaro',
            '__tcfapi'            => 'TCF-Compatible CMP',  // IAB TCF v2 API
            '__cmp'               => 'TCF-Compatible CMP',
        ];

        preg_match_all('/<script[^>]+src=["\x27]([^"\x27]+)["\x27][^>]*>/si', $html, $srcMatches);

        $trackers        = [];   // all trackers (html + csp)
        $trackerSrcOnly  = [];   // trackers hardcoded in <script src> (definite load)
        $cookieBanners   = [];

        // 1. External script src attributes — definite loads
        foreach ($srcMatches[1] ?? [] as $src) {
            foreach ($knownTrackers as $pattern => $name) {
                if (str_contains($src, $pattern)) {
                    $trackers[]       = $name;
                    $trackerSrcOnly[] = $name;
                }
            }
            foreach ($knownCmps as $pattern => $name) {
                if (str_contains($src, $pattern)) {
                    $cookieBanners[] = $name;
                }
            }
        }

        // 2. Content-Security-Policy header — catches CMPs loaded by SPA bundles.
        // Trackers found here are only ALLOWED, not necessarily loaded without consent.
        if ($cspHeader) {
            foreach ($knownCmps as $pattern => $name) {
                if (str_contains($cspHeader, $pattern)) {
                    $cookieBanners[] = $name;
                }
            }
            foreach ($knownTrackers as $pattern => $name) {
                if (str_contains($cspHeader, $pattern)) {
                    $trackers[] = $name; // add to full list but NOT to trackerSrcOnly
                }
            }
        }

        // 3. Inline script content
        preg_match_all('/<script(?![^>]+src=)[^>]*>(.*?)<\/script>/si', $html, $inlineMatches);
        $inlineContent = implode(' ', $inlineMatches[1] ?? []);
        if ($inlineContent) {
            foreach ($inlineCmpPatterns as $pattern => $name) {
                if (str_contains($inlineContent, $pattern)) {
                    $cookieBanners[] = $name;
                }
            }
        }

        // 3b. Custom JS consent patterns — common in hand-rolled implementations (React/Vue SPAs)
        $customConsentPatterns = [
            'cookie-consent', 'cookieConsent', 'cookie_consent',
            'getConsent', 'setConsent', 'hasConsent',
            'consentGiven', 'consent_given',
            'analyticsConsent', 'analytics_consent',
            'cookieAccepted', 'cookie_accepted',
            'gdprConsent', 'gdpr_consent',
        ];
        if ($inlineContent) {
            foreach ($customConsentPatterns as $pattern) {
                if (str_contains($inlineContent, $pattern)) {
                    $cookieBanners[] = 'Custom Consent';
                    break;
                }
            }
        }
        // Also check localStorage consent patterns in the full HTML (SPA bundles)
        foreach ($customConsentPatterns as $pattern) {
            if (str_contains($html, $pattern)) {
                $cookieBanners[] = 'Custom Consent';
                break;
            }
        }

        // 4. HTML data attributes common in CMP integrations
        foreach ($knownCmps as $pattern => $name) {
            // e.g. data-cookieconsent, data-iub, data-axeptio
            $slug = explode('.', $pattern)[0];
            if (preg_match('/data-' . preg_quote($slug, '/') . '/i', $html)) {
                $cookieBanners[] = $name;
            }
        }

        // Trackers found only in CSP (allowlist) but NOT in actual <script src> tags.
        // These may be loaded conditionally by JS — do NOT count as "definitely active".
        $trackersInHtml = array_unique($trackers);
        $bannersInHtml  = array_unique($cookieBanners);

        return [
            'total'              => count($srcMatches[1] ?? []),
            'trackers'           => $trackersInHtml, // found in <script src> or CSP
            'trackers_in_html'   => array_unique($trackerSrcOnly ?? []), // only from <script src>
            'cookie_banners'     => $bannersInHtml,
        ];
    }

    private function extractLinks(string $html, string $baseUrl): array
    {
        $domain        = parse_url($baseUrl, PHP_URL_HOST) ?? '';
        $internal      = 0;
        $external      = 0;
        $privacyPolicy = false;
        $cookiePolicy  = false;

        preg_match_all('/<a\s+[^>]*href=["\x27]([^"\x27]+)["\x27][^>]*>(.*?)<\/a>/si', $html, $matches);

        foreach ($matches[1] ?? [] as $i => $href) {
            $text = strtolower(strip_tags($matches[2][$i] ?? ''));
            $href = strtolower($href);

            if (str_contains($href, $domain) || str_starts_with($href, '/')) {
                $internal++;
            } elseif (str_starts_with($href, 'http')) {
                $external++;
            }

            if (str_contains($href, 'privacy') || str_contains($text, 'privacy')) {
                $privacyPolicy = true;
            }
            if (str_contains($href, 'cookie') || str_contains($text, 'cookie')) {
                $cookiePolicy = true;
            }
        }

        return [
            'internal'       => $internal,
            'external'       => $external,
            'privacy_policy' => $privacyPolicy,
            'cookie_policy'  => $cookiePolicy,
        ];
    }

    /**
     * Extract social profile URLs from the page — works for both static HTML and SPAs.
     * Searches <a href> tags AND the raw page source (JS bundles often contain profile URLs).
     */
    private function extractSocialLinks(string $html): array
    {
        $platforms = [
            'linkedin'  => ['linkedin.com/company/', 'linkedin.com/in/'],
            'facebook'  => ['facebook.com/'],
            'instagram' => ['instagram.com/'],
            'twitter'   => ['twitter.com/', 'x.com/'],
            'youtube'   => ['youtube.com/channel/', 'youtube.com/c/', 'youtube.com/@'],
            'tiktok'    => ['tiktok.com/@'],
        ];

        $found = [];

        // Search raw page source — captures URLs in JS bundles, JSON-LD, meta tags, etc.
        // Handles both https://domain/path and https://www.domain/path forms.
        foreach ($platforms as $platform => $patterns) {
            foreach ($patterns as $pattern) {
                $escaped = preg_quote($pattern, '/');
                // Allow optional www. before the domain
                if (preg_match('/https?:\/\/(?:www\.)?' . $escaped . '([A-Za-z0-9._\-@]+)/i', $html, $m)) {
                    $slug = rtrim($m[1], '/?#');
                    $blacklist = ['sharer', 'share', 'dialog', 'plugins', 'tr', 'ads', 'business', 'legal'];
                    if ($slug && strlen($slug) > 1 && ! in_array(strtolower($slug), $blacklist)) {
                        $found[$platform] = 'https://' . $pattern . $slug;
                        break;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Detect if the page source contains any evidence of a cookie consent implementation.
     * Useful for JS-heavy SPAs where the banner is rendered by JavaScript.
     */
    private function detectConsentInSource(string $html): bool
    {
        $patterns = [
            'cookie-consent',    // localStorage key pattern (React/Vue apps)
            'cookieConsent',
            'cookie_consent',
            'CookieConsent',
            'analyticsConsent',
            'analytics_consent',
            'consentGiven',
            'gdprConsent',
            '__tcfapi',           // IAB TCF v2
            '__cmp',              // IAB CMP
            'OptanonWrapper',     // OneTrust
            '_iub',               // Iubenda
            'ConsentManager',
            'ConsentBanner',
            'CookiePolicy',
            'PrivacyPolicy',
            'privacy-policy',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($html, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function extractLang(string $html): ?string
    {
        if (preg_match('/<html[^>]+lang=["\x27]([^"\x27]+)["\x27][^>]*>/si', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractSchemaOrg(string $html): array
    {
        preg_match_all('/<script[^>]+type=["\x27]application\/ld\+json["\x27][^>]*>(.*?)<\/script>/si', $html, $matches);

        $schemas = [];
        foreach ($matches[1] ?? [] as $json) {
            try {
                $data = json_decode(trim($json), true, 10, JSON_THROW_ON_ERROR);
                foreach ($this->collectSchemaTypes($data) as $type) {
                    $schemas[] = $type;
                }
            } catch (\Throwable) {
                // skip malformed JSON-LD
            }
        }

        $schemas = array_values(array_unique($schemas));

        return [
            'found'              => ! empty($schemas),
            'types'              => $schemas,
            'has_local_business' => in_array('LocalBusiness', $schemas, true)
                || in_array('Organization', $schemas, true),
        ];
    }

    /**
     * Walk a JSON-LD payload and collect every @type value as flat strings.
     * Handles single objects, arrays of objects, @graph wrappers, and
     * multi-type entries like "@type": ["LocalBusiness", "Psychologist"].
     *
     * @return list<string>
     */
    private function collectSchemaTypes(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $types = [];

        if (isset($data['@type'])) {
            foreach ((array) $data['@type'] as $t) {
                if (is_string($t) && $t !== '') {
                    $types[] = $t;
                }
            }
        }

        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                $types = array_merge($types, $this->collectSchemaTypes($node));
            }
        }

        // Top-level array of nodes (no @graph wrapper)
        if (! isset($data['@type']) && ! isset($data['@graph']) && array_is_list($data)) {
            foreach ($data as $node) {
                $types = array_merge($types, $this->collectSchemaTypes($node));
            }
        }

        return $types;
    }

    private function fetchRobotsTxt(string $url): array
    {
        try {
            $base     = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
            $response = Http::timeout(5)->get("{$base}/robots.txt");

            if ($response->successful() && str_contains($response->body(), 'User-agent')) {
                $body = $response->body();
                return [
                    'found'           => true,
                    'disallows_all'   => (bool) preg_match('/^Disallow:\s*\/\s*$/m', $body),
                    'has_sitemap_ref' => str_contains(strtolower($body), 'sitemap:'),
                ];
            }
        } catch (\Throwable) {}

        return ['found' => false, 'disallows_all' => false, 'has_sitemap_ref' => false];
    }

    private function detectSitemap(string $url): bool
    {
        try {
            $base = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
            if (Http::timeout(5)->get("{$base}/sitemap.xml")->successful()) {
                return true;
            }
            return Http::timeout(5)->get("{$base}/sitemap_index.xml")->successful();
        } catch (\Throwable) {}
        return false;
    }

    private function detectLlmsTxt(string $url): array
    {
        try {
            $base = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
            $response = Http::timeout(5)->get("{$base}/llms.txt");
            if ($response->successful() && strlen($response->body()) > 10) {
                return ['found' => true, 'size' => strlen($response->body())];
            }
        } catch (\Throwable) {}
        return ['found' => false];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function attr(string $attrs, string $name): ?string
    {
        if (preg_match('/' . preg_quote($name, '/') . '\s*=\s*["\x27]([^"\x27]*)["\x27]/si', $attrs, $m)) {
            return trim(html_entity_decode($m[1]));
        }
        return null;
    }

    private function normalizeUrl(string $url): string
    {
        if (! str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }
        return rtrim($url, '/');
    }

    private function emptyResult(string $url, string $error): array
    {
        return [
            'url'         => $url,
            'status_code' => null,
            'html_length' => 0,
            'is_js_heavy' => null,
            'meta'        => [],
            'headings'    => [],
            'images'      => [],
            'scripts'     => [],
            'links'       => [],
            'lang'        => null,
            'schema_org'  => [],
            'robots_txt'  => [],
            'sitemap'     => false,
            'llms_txt'    => ['found' => false],
            'error'       => $error,
        ];
    }
}
