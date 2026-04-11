<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Generation\Contracts\TrendResearcherInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementazione Perplexity del researcher di trend.
 *
 * Replica 3 servizi Python:
 * - perplexity_service.py           → search_trends, fetch_url_content, analyze_competitor
 * - perplexity_scheduling_research.py → research_optimal_schedule, _get_default_schedule
 * - perplexity_content_mix_research.py → research_optimal_content_mix, _get_default_content_mix
 *
 * Cache risultati 30 giorni con Laravel Cache (Redis).
 */
final class PerplexityTrendResearcher implements TrendResearcherInterface
{
    private const API_URL    = 'https://api.perplexity.ai/chat/completions';
    private const MODEL      = 'sonar';
    private const CACHE_DAYS = 30;
    private const MAX_RETRIES = 3;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.perplexity.api_key', env('PERPLEXITY_API_KEY', ''));
    }

    /**
     * Usa la chiave del brand se presente, altrimenti fallback al config di sistema.
     */
    public function withBrand(?Brand $brand): static
    {
        if ($brand) {
            $key = app(BrandApiKeyService::class)->get($brand, BrandApiKeyService::PERPLEXITY_API_KEY);
            if ($key) {
                $clone         = clone $this;
                $clone->apiKey = $key;
                return $clone;
            }
        }
        return $this;
    }

    // ══════════════════════════════════════════════════════════
    //  TrendResearcherInterface
    // ══════════════════════════════════════════════════════════

    /**
     * Analizza URL di riferimento e restituisce contesto (fetch_url_content per ogni URL).
     */
    public function analyzeReferenceUrls(array $urls): array
    {
        $results = [];

        foreach (array_slice($urls, 0, 5) as $url) {
            $content = $this->fetchUrlContent($url);
            if ($content) {
                $results[] = ['url' => $url, 'content' => $content];
            }
        }

        return $results;
    }

    /**
     * Ricerca trend e contesto per un brand/settore (search_trends).
     */
    public function research(array $params): array
    {
        $sector        = $params['sector'] ?? '';
        $brandName     = $params['brand_name'] ?? '';
        $targetAudience = $params['target_audience'] ?? '';
        $platforms     = $params['platforms'] ?? [];

        $trends    = $this->searchTrends($sector, $brandName);
        $schedules = $this->researchAllPlatformsSchedule(
            businessType:  'B2B',
            sector:        $sector,
            buyerPersona:  $targetAudience ?: 'professionista',
            platforms:     $platforms,
        );
        $contentMix = $this->researchAllPlatformsContentMix(
            businessType:  'B2B',
            sector:        $sector,
            buyerPersona:  $targetAudience ?: 'professionista',
            platforms:     $platforms,
        );

        return [
            'trends'      => $trends,
            'schedules'   => $schedules,
            'content_mix' => $contentMix,
        ];
    }

    /**
     * Analizza il sito web di un brand (fetch_url_content).
     */
    public function analyzeBrandWebsite(string $url): array
    {
        $content = $this->fetchUrlContent($url);

        return $content ? ['url' => $url, 'analysis' => $content] : [];
    }

    // ══════════════════════════════════════════════════════════
    //  perplexity_service.py — search_trends
    // ══════════════════════════════════════════════════════════

    /**
     * Cerca trend del settore con Perplexity.
     * Replica esatta di search_trends().
     */
    public function searchTrends(string $sector, string $brandName = ''): string
    {
        if (! $this->apiKey) {
            return '';
        }

        $cacheKey = 'perplexity_trends:' . md5("{$sector}_{$brandName}");

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_DAYS), function () use ($sector): string {
            $response = $this->callPerplexity(
                "Quali sono i trend attuali nel settore {$sector}? Focus su temi per content marketing B2B. Rispondi in italiano, max 500 parole."
            );

            return $response ?? '';
        });
    }

    /**
     * Estrae contenuto da URL via Perplexity.
     * Replica esatta di fetch_url_content().
     */
    public function fetchUrlContent(string $url): string
    {
        if (! $this->apiKey) {
            return '';
        }

        $response = $this->callPerplexity(
            "Analizza questa pagina e estrai i contenuti principali, tone of voice e messaggi chiave: {$url}. Rispondi in italiano."
        );

        return $response ?? '';
    }

    /**
     * Analizza competitor.
     * Replica esatta di analyze_competitor().
     */
    public function analyzeCompetitor(string $url): string
    {
        if (! $this->apiKey) {
            return '';
        }

        $response = $this->callPerplexity(
            "Analizza la strategia social/content di questo competitor: {$url}. Identifica: temi principali, frequenza, tone of voice, punti di forza. In italiano."
        );

        return $response ?? '';
    }

    // ══════════════════════════════════════════════════════════
    //  perplexity_scheduling_research.py — research_optimal_schedule
    // ══════════════════════════════════════════════════════════

    /**
     * Ricerca con Perplexity gli orari migliori per pubblicare.
     * Replica esatta di research_optimal_schedule().
     * Cache 30 giorni.
     */
    public function researchOptimalSchedule(
        string $businessType,
        string $sector,
        string $platform,
        string $buyerPersona,
        string $country = 'Italia',
        string $objective = 'engagement',
    ): array {
        $cacheKey = 'perplexity_schedule:' . md5(
            strtolower(str_replace(' ', '_', "{$businessType}_{$sector}_{$platform}_{$buyerPersona}_{$country}_{$objective}"))
        );

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_DAYS), function () use ($businessType, $sector, $platform, $buyerPersona, $country, $objective): array {
            if (! $this->apiKey) {
                Log::warning('[PERPLEXITY] API key not configured, using defaults');
                return $this->getDefaultSchedule($platform);
            }

            $year = now()->year;

            $query = <<<QUERY
Ricerca, in base ai dati più recenti disponibili (studi 2024-{$year}, analisi di milioni di post e report di social media marketing), i MIGLIORI giorni e orari per pubblicare su {$platform} nel {$year} per:
- Tipo azienda: {$businessType}
- Settore: {$sector}
- Target / Buyer Persona: {$buyerPersona}
- Paese: {$country}
- Obiettivo principale: {$objective}

LINEE GUIDA PER LA RICERCA:
- Usa solo fonti con dati aggregati e affidabili (analisi di grandi volumi di post, report di piattaforme/tool di social media).
- Considera che:
  - Contenuti B2B e professionali tendono a performare meglio nei giorni feriali e in orario lavorativo/pausa pranzo.
  - Contenuti orientati a studenti, genitori o pubblico consumer possono avere picchi anche nel tardo pomeriggio/sera e NEL WEEKEND.
  - Per Instagram e Facebook B2C il weekend (sabato/domenica) è spesso molto efficace.
- Adatta la risposta al fuso orario locale del paese indicato.
- NON limitarti a martedì-mercoledì-giovedì se i dati suggeriscono altri giorni migliori.

FORMATO RISPOSTA - Rispondi SOLO con questo JSON:
{
  "best_days": ["lunedì", "mercoledì", "venerdì"],
  "best_days_numbers": [0, 2, 4],
  "best_times": ["09:00", "12:30", "18:00"],
  "avoid_days": ["domenica"],
  "avoid_times": ["dopo le 22:00"],
  "confidence": "high",
  "notes": "Breve spiegazione (max 2 frasi) con pattern principali e fonti."
}

REGOLE:
- best_days_numbers: 0=lunedì, 1=martedì, 2=mercoledì, 3=giovedì, 4=venerdì, 5=sabato, 6=domenica
- best_times in formato 24h HH:MM, ordinati dal più precoce al più tardo
- Includi weekend se appropriato per il target
- Rispondi SOLO con il JSON, niente altro testo
QUERY;

            try {
                $content = $this->callPerplexityJson($query);

                if ($content === null) {
                    return $this->getDefaultSchedule($platform);
                }

                $result = $this->parseJsonResponse($content);

                $result['source']       = 'perplexity';
                $result['last_updated'] = now()->toIso8601String();
                $result['query_params'] = compact('businessType', 'sector', 'platform', 'buyerPersona', 'country', 'objective');

                Log::info("[PERPLEXITY] Successfully researched schedule for {$platform}/{$businessType}/{$sector}");

                return $result;
            } catch (\Throwable $e) {
                Log::error("[PERPLEXITY] Error: {$e->getMessage()}");

                return $this->getDefaultSchedule($platform);
            }
        });
    }

    /**
     * Ricerca orari per tutte le piattaforme.
     * Replica di research_all_platforms_schedule().
     */
    public function researchAllPlatformsSchedule(
        string $businessType,
        string $sector,
        string $buyerPersona,
        array  $platforms,
        string $country = 'Italia',
        string $objective = 'engagement',
    ): array {
        $results = [];

        foreach ($platforms as $platform) {
            $results[$platform] = $this->researchOptimalSchedule(
                $businessType, $sector, $platform, $buyerPersona, $country, $objective,
            );
        }

        return $results;
    }

    // ══════════════════════════════════════════════════════════
    //  perplexity_content_mix_research.py — research_optimal_content_mix
    // ══════════════════════════════════════════════════════════

    /**
     * Ricerca il mix ottimale di formati contenuto per una piattaforma.
     * Replica esatta di research_optimal_content_mix().
     * Cache 30 giorni. Max 3 retry.
     */
    public function researchOptimalContentMix(
        string $businessType,
        string $sector,
        string $platform,
        string $buyerPersona,
        string $country = 'Italia',
        string $objective = 'engagement',
    ): array {
        $cacheKey = 'perplexity_mix:' . md5(
            strtolower(str_replace(' ', '_', "mix_{$businessType}_{$sector}_{$platform}_{$buyerPersona}_{$country}_{$objective}"))
        );

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_DAYS), function () use ($businessType, $sector, $platform, $buyerPersona, $country, $objective): array {
            if (! $this->apiKey) {
                Log::warning('[PERPLEXITY-MIX] API key not configured, using defaults');
                return $this->getDefaultContentMix($platform);
            }

            $year = now()->year;

            $query = <<<QUERY
Ricerca le statistiche e best practices più recenti ({$year}) per la distribuzione ottimale dei formati di contenuto su {$platform}.

Contesto:
- Tipo azienda: {$businessType}
- Settore: {$sector}
- Target/Buyer Persona: {$buyerPersona}
- Paese: {$country}
- Obiettivo principale: {$objective}

Devo sapere:
1. Quale percentuale di POST classici (immagine + testo lungo) pubblicare settimanalmente
2. Quale percentuale di STORIES (contenuti effimeri 24h) pubblicare
3. Quale percentuale di REELS/video brevi pubblicare
4. Quanti contenuti totali a settimana sono raccomandati per questa piattaforma
5. Quali tipi di contenuto funzionano meglio per ogni formato nel settore {$sector}

Rispondi SOLO con un JSON valido in questo formato esatto:
{
    "platform": "{$platform}",
    "supports_stories": true,
    "supports_reels": true,
    "recommended_weekly_total": 7,
    "format_mix": {
        "post_percentage": 50,
        "story_percentage": 30,
        "reel_percentage": 20
    },
    "format_weekly_count": {
        "posts": 4,
        "stories": 2,
        "reels": 1
    },
    "best_content_ideas": {
        "posts": ["case study", "infografiche", "annunci"],
        "stories": ["behind the scenes", "sondaggi", "Q&A"],
        "reels": ["tutorial veloci", "trend", "tips"]
    },
    "sector_specific_tips": "Consigli specifici per il settore",
    "confidence": "high",
    "sources_summary": "Breve riassunto delle fonti consultate"
}

IMPORTANTE:
- Se la piattaforma NON supporta un formato, metti percentage a 0 e supports_* a false
- LinkedIn NON supporta stories né reels nativi
- Google Business NON supporta stories né reels
- TikTok è SOLO reels (100%)
- Le percentuali devono sommare a 100
- Rispondi SOLO con il JSON, niente altro testo
QUERY;

            // Max 3 retry (come il Python)
            $lastException = null;
            for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
                try {
                    $content = $this->callPerplexityJson($query);

                    if ($content === null) {
                        throw new \RuntimeException('Empty response');
                    }

                    $result = $this->parseJsonResponse($content);

                    $result['source']       = 'perplexity';
                    $result['last_updated'] = now()->toIso8601String();
                    $result['query_params'] = compact('businessType', 'sector', 'platform', 'buyerPersona', 'country', 'objective');

                    Log::info("[PERPLEXITY-MIX] Successfully researched content mix for {$platform}/{$sector}");

                    return $result;
                } catch (\Throwable $e) {
                    $lastException = $e;
                    Log::warning("[PERPLEXITY-MIX] Error (attempt " . ($attempt + 1) . "/" . self::MAX_RETRIES . "): {$e->getMessage()}");
                    if ($attempt < self::MAX_RETRIES - 1) {
                        sleep(2 * ($attempt + 1));
                    }
                }
            }

            Log::error("[PERPLEXITY-MIX] All " . self::MAX_RETRIES . " attempts failed for {$platform}");

            return $this->getDefaultContentMix($platform);
        });
    }

    /**
     * Ricerca mix contenuti per tutte le piattaforme.
     * Replica di research_all_platforms_content_mix().
     */
    public function researchAllPlatformsContentMix(
        string $businessType,
        string $sector,
        string $buyerPersona,
        array  $platforms,
        string $country = 'Italia',
        string $objective = 'engagement',
    ): array {
        $results = [];

        foreach ($platforms as $platform) {
            $results[$platform] = $this->researchOptimalContentMix(
                $businessType, $sector, $platform, $buyerPersona, $country, $objective,
            );
        }

        return $results;
    }

    // ══════════════════════════════════════════════════════════
    //  Default fallbacks (replica esatta dal Python)
    // ══════════════════════════════════════════════════════════

    /**
     * Schedule di fallback — replica di _get_default_schedule().
     */
    public function getDefaultSchedule(string $platform): array
    {
        $defaults = [
            'instagram' => [
                'best_days'         => ['martedì', 'giovedì', 'sabato'],
                'best_days_numbers' => [1, 3, 5],
                'best_times'        => ['12:00', '19:00', '21:00'],
                'avoid_days'        => ['lunedì mattina'],
                'avoid_times'       => ['03:00-06:00'],
                'confidence'        => 'medium',
                'notes'             => 'Default basato su statistiche generali Italia',
            ],
            'linkedin' => [
                'best_days'         => ['martedì', 'mercoledì', 'giovedì'],
                'best_days_numbers' => [1, 2, 3],
                'best_times'        => ['07:30', '08:30', '12:30'],
                'avoid_days'        => ['weekend'],
                'avoid_times'       => ['sera', 'notte'],
                'confidence'        => 'medium',
                'notes'             => 'Default B2B Italia',
            ],
            'facebook' => [
                'best_days'         => ['mercoledì', 'venerdì', 'sabato'],
                'best_days_numbers' => [2, 4, 5],
                'best_times'        => ['13:00', '16:00', '20:00'],
                'avoid_days'        => ['lunedì'],
                'avoid_times'       => ['mattina presto'],
                'confidence'        => 'medium',
                'notes'             => 'Default engagement Italia',
            ],
            'google_business' => [
                'best_days'         => ['lunedì', 'giovedì'],
                'best_days_numbers' => [0, 3],
                'best_times'        => ['10:00', '14:00'],
                'avoid_days'        => ['domenica'],
                'avoid_times'       => ['sera'],
                'confidence'        => 'medium',
                'notes'             => 'Default local business',
            ],
            'twitter' => [
                'best_days'         => ['martedì', 'mercoledì', 'giovedì'],
                'best_days_numbers' => [1, 2, 3],
                'best_times'        => ['08:00', '12:00', '17:00'],
                'avoid_days'        => ['weekend'],
                'avoid_times'       => ['notte'],
                'confidence'        => 'medium',
                'notes'             => 'Default engagement',
            ],
        ];

        $default = $defaults[strtolower($platform)] ?? [
            'best_days'         => ['martedì', 'giovedì'],
            'best_days_numbers' => [1, 3],
            'best_times'        => ['10:00', '14:00'],
            'avoid_days'        => [],
            'avoid_times'       => [],
            'confidence'        => 'low',
            'notes'             => 'Default generico',
        ];

        $default['source']       = 'default_fallback';
        $default['last_updated'] = now()->toIso8601String();

        return $default;
    }

    /**
     * Mix contenuti di fallback — replica di _get_default_content_mix().
     */
    public function getDefaultContentMix(string $platform): array
    {
        $defaults = [
            'instagram' => [
                'platform'                 => 'instagram',
                'supports_stories'         => true,
                'supports_reels'           => true,
                'recommended_weekly_total'  => 7,
                'format_mix'               => ['post_percentage' => 45, 'story_percentage' => 35, 'reel_percentage' => 20],
                'format_weekly_count'      => ['posts' => 3, 'stories' => 3, 'reels' => 1],
                'best_content_ideas'       => ['posts' => ['case study', 'infografiche'], 'stories' => ['behind the scenes', 'sondaggi'], 'reels' => ['tutorial veloci', 'tips']],
                'sector_specific_tips'     => null,
                'confidence'               => 'medium',
            ],
            'facebook' => [
                'platform'                 => 'facebook',
                'supports_stories'         => true,
                'supports_reels'           => true,
                'recommended_weekly_total'  => 5,
                'format_mix'               => ['post_percentage' => 60, 'story_percentage' => 25, 'reel_percentage' => 15],
                'format_weekly_count'      => ['posts' => 3, 'stories' => 1, 'reels' => 1],
                'best_content_ideas'       => ['posts' => [], 'stories' => [], 'reels' => []],
                'sector_specific_tips'     => null,
                'confidence'               => 'medium',
            ],
            'linkedin' => [
                'platform'                 => 'linkedin',
                'supports_stories'         => false,
                'supports_reels'           => false,
                'recommended_weekly_total'  => 4,
                'format_mix'               => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'      => ['posts' => 4, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'       => ['posts' => ['thought leadership', 'industry insights']],
                'sector_specific_tips'     => null,
                'confidence'               => 'medium',
            ],
            'tiktok' => [
                'platform'                 => 'tiktok',
                'supports_stories'         => false,
                'supports_reels'           => true,
                'recommended_weekly_total'  => 5,
                'format_mix'               => ['post_percentage' => 0, 'story_percentage' => 0, 'reel_percentage' => 100],
                'format_weekly_count'      => ['posts' => 0, 'stories' => 0, 'reels' => 5],
                'best_content_ideas'       => ['reels' => ['trend', 'tutorial', 'tips']],
                'sector_specific_tips'     => null,
                'confidence'               => 'medium',
            ],
            'twitter' => [
                'platform'                 => 'twitter',
                'supports_stories'         => false,
                'supports_reels'           => false,
                'recommended_weekly_total'  => 7,
                'format_mix'               => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'      => ['posts' => 7, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'       => ['posts' => ['hot takes', 'threads', 'engagement questions']],
                'sector_specific_tips'     => null,
                'confidence'               => 'medium',
            ],
            'google_business' => [
                'platform'                 => 'google_business',
                'supports_stories'         => false,
                'supports_reels'           => false,
                'recommended_weekly_total'  => 3,
                'format_mix'               => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'      => ['posts' => 3, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'       => ['posts' => ['promozioni', 'novità', 'eventi']],
                'sector_specific_tips'     => null,
                'confidence'               => 'medium',
            ],
        ];

        $key = strtolower($platform);

        $default = $defaults[$key] ?? [
            'platform'                 => $key,
            'supports_stories'         => false,
            'supports_reels'           => false,
            'recommended_weekly_total'  => 5,
            'format_mix'               => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
            'format_weekly_count'      => ['posts' => 5, 'stories' => 0, 'reels' => 0],
            'best_content_ideas'       => ['posts' => []],
            'sector_specific_tips'     => null,
            'confidence'               => 'low',
        ];

        $default['source']       = 'default_fallback';
        $default['last_updated'] = now()->toIso8601String();

        return $default;
    }

    /**
     * Svuota cache Perplexity (clear_cache).
     */
    public function clearCache(): void
    {
        // Laravel Cache tags non disponibili su tutti i driver,
        // usiamo pattern-based flush su Redis.
        try {
            $redis = Cache::getStore()->getRedis();
            $prefix = config('cache.prefix', 'laravel_cache');

            foreach (['perplexity_trends:*', 'perplexity_schedule:*', 'perplexity_mix:*'] as $pattern) {
                $keys = $redis->keys("{$prefix}:{$pattern}");
                if (! empty($keys)) {
                    $redis->del(...$keys);
                }
            }

            Log::info('[PERPLEXITY] Cache cleared');
        } catch (\Throwable $e) {
            Log::warning("[PERPLEXITY] Cache clear error: {$e->getMessage()}");
        }
    }

    /**
     * Statistiche cache (get_cache_stats).
     */
    public function getCacheStats(): array
    {
        try {
            $redis  = Cache::getStore()->getRedis();
            $prefix = config('cache.prefix', 'laravel_cache');

            $count = 0;
            foreach (['perplexity_trends:*', 'perplexity_schedule:*', 'perplexity_mix:*'] as $pattern) {
                $count += count($redis->keys("{$prefix}:{$pattern}"));
            }

            return ['entries' => $count];
        } catch (\Throwable) {
            return ['entries' => 0];
        }
    }

    // ══════════════════════════════════════════════════════════
    //  Private HTTP helpers
    // ══════════════════════════════════════════════════════════

    /**
     * Chiama Perplexity con un messaggio singolo (user-only).
     * Usato da search_trends, fetch_url_content, analyze_competitor.
     */
    private function callPerplexity(string $message): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ])
                ->timeout(30)
                ->post(self::API_URL, [
                    'model'    => self::MODEL,
                    'messages' => [
                        ['role' => 'user', 'content' => $message],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::error('[PERPLEXITY] API error: ' . $response->status() . ' — ' . $response->body());
        } catch (\Throwable $e) {
            Log::error("[PERPLEXITY] Error: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Chiama Perplexity con system prompt JSON-only (per schedule/content-mix).
     */
    private function callPerplexityJson(string $query): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ])
                ->timeout(60)
                ->post(self::API_URL, [
                    'model'       => self::MODEL,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'Sei un esperto di social media marketing. Rispondi sempre e solo con JSON valido, senza markdown o altro testo.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $query,
                        ],
                    ],
                    'temperature' => 0.1,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::error('[PERPLEXITY] API error: ' . $response->status());
        } catch (\Throwable $e) {
            Log::error("[PERPLEXITY] Error: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Parsa risposta JSON rimuovendo backtick markdown (stessa logica Python).
     */
    private function parseJsonResponse(string $content): array
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $parts   = explode('```', $content);
            $content = $parts[1] ?? $content;
            if (str_starts_with($content, 'json')) {
                $content = substr($content, 4);
            }
        }

        return json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);
    }
}
