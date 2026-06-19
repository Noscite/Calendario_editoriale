<?php

declare(strict_types=1);

namespace App\Domain\Social\Jobs;

use App\Domain\Post\Enums\Platform;
use App\Domain\Social\Models\PostPublication;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Models\SocialMetric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Job per la raccolta periodica delle metriche social (analytics).
 *
 * Schedulato ogni 6 ore via routes/console.php.
 *
 * Per ogni PostPublication con status = 'published' e published_at
 * nelle ultime 30 giorni, recupera:
 *   - likes, comments, shares, reach, impressions
 *
 * Gestisce i rate limit delle piattaforme:
 *   - Meta (Facebook): 200 req/ora
 *   - LinkedIn:        100 req/ora
 *
 * Instagram e GBP: TODO — API meno stabili.
 */
class CollectSocialMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300; // 5 minuti

    // Rate limit per piattaforma (req/ora)
    private const RATE_LIMITS = [
        'facebook'       => 200,
        'instagram'      => 200, // TODO
        'linkedin'       => 100,
        'google_business' => 50, // TODO
    ];

    public function handle(): void
    {
        Log::info('[METRICS] Avvio raccolta metriche social');

        $cutoff = now()->subDays(30);

        // Recupera pubblicazioni recenti che hanno un ID esterno
        $publications = PostPublication::with(['socialConnection', 'post'])
            ->where('status', 'published')
            ->where('published_at', '>=', $cutoff)
            ->whereNotNull('external_post_id')
            ->whereNotNull('social_connection_id')
            ->get();

        Log::info("[METRICS] {$publications->count()} pubblicazioni da analizzare");

        $counters = ['facebook' => 0, 'linkedin' => 0, 'skipped' => 0];

        foreach ($publications as $publication) {
            $connection = $publication->socialConnection;
            if (!$connection || !$connection->is_active) {
                $counters['skipped']++;
                continue;
            }

            $platform = $connection->platform->value;
            $limit    = self::RATE_LIMITS[$platform] ?? 50;

            // Controlla rate limit per piattaforma
            if ($this->isThrottled($platform, $limit)) {
                Log::info("[METRICS] Rate limit raggiunto per {$platform} — pausa");
                sleep(10);
                if ($this->isThrottled($platform, $limit)) {
                    $counters['skipped']++;
                    continue;
                }
            }

            $metrics = match ($connection->platform) {
                Platform::Facebook => $this->collectFacebookMetrics($publication, $connection),
                Platform::LinkedIn => $this->collectLinkedInMetrics($publication, $connection),
                default            => null, // TODO: Instagram, GBP
            };

            if ($metrics !== null) {
                $this->saveMetrics($publication, $metrics);
                $counters[$platform] = ($counters[$platform] ?? 0) + 1;
                $this->hitRateLimit($platform);
            } else {
                $counters['skipped']++;
            }
        }

        Log::info('[METRICS] Raccolta completata', $counters);
    }

    // ──────────────────────────────────────────────────────────
    //  Facebook metrics
    // ──────────────────────────────────────────────────────────

    /**
     * Recupera metriche da Facebook Graph API per un post pubblicato.
     * Endpoint: GET /{post_id}/insights?metric=post_impressions,...
     */
    private function collectFacebookMetrics(PostPublication $publication, SocialConnection $connection): ?array
    {
        $postId      = $publication->external_post_id;
        $accessToken = $connection->access_token;

        try {
            // Versione Graph API centralizzata in config (default v18.0).
            $base = 'https://graph.facebook.com/' . config('services.meta.graph_version');

            // Recupera insights del post
            $insightsResponse = Http::get("{$base}/{$postId}/insights", [
                'metric'       => 'post_impressions,post_impressions_unique,post_engaged_users',
                'access_token' => $accessToken,
            ]);

            // Recupera reactions/comments/shares
            $postResponse = Http::get("{$base}/{$postId}", [
                'fields'       => 'likes.summary(true),comments.summary(true),shares',
                'access_token' => $accessToken,
            ]);

            if (!$insightsResponse->successful() && !$postResponse->successful()) {
                Log::warning("[METRICS] Facebook: errore per post {$postId}", [
                    'status' => $insightsResponse->status(),
                ]);
                return null;
            }

            $insightsData = $insightsResponse->successful() ? $insightsResponse->json('data', []) : [];
            $postData     = $postResponse->successful() ? $postResponse->json() : [];

            // Estrai metriche dagli insights
            $impressions = 0;
            $reach       = 0;
            $engagement  = 0;

            foreach ($insightsData as $insight) {
                $value = $insight['values'][0]['value'] ?? 0;
                match ($insight['name'] ?? '') {
                    'post_impressions'        => $impressions = (int) $value,
                    'post_impressions_unique' => $reach = (int) $value,
                    'post_engaged_users'      => $engagement = (int) $value,
                    default                   => null,
                };
            }

            $likes    = (int) ($postData['likes']['summary']['total_count'] ?? 0);
            $comments = (int) ($postData['comments']['summary']['total_count'] ?? 0);
            $shares   = (int) ($postData['shares']['count'] ?? 0);

            Log::info("[METRICS] Facebook: metriche raccolte per post {$postId}", [
                'impressions' => $impressions,
                'reach'       => $reach,
                'likes'       => $likes,
            ]);

            return compact('impressions', 'reach', 'engagement', 'likes', 'comments', 'shares');

        } catch (\Throwable $e) {
            Log::error("[METRICS] Facebook: eccezione per post {$postId}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────
    //  LinkedIn metrics
    // ──────────────────────────────────────────────────────────

    /**
     * Recupera metriche da LinkedIn API per un post pubblicato.
     * Endpoint: GET /socialActions/{ugcPostUrn}/socialMetadata
     */
    private function collectLinkedInMetrics(PostPublication $publication, SocialConnection $connection): ?array
    {
        $ugcPostId   = $publication->external_post_id;
        $accessToken = $connection->access_token;

        try {
            // Encode URN per URL
            $encodedUrn = urlencode($ugcPostId);

            $response = Http::withHeaders([
                'Authorization'               => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version'   => '2.0.0',
            ])->get("https://api.linkedin.com/v2/socialActions/{$encodedUrn}");

            if (!$response->successful()) {
                Log::warning("[METRICS] LinkedIn: errore per post {$ugcPostId}", [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();

            $likes    = (int) ($data['likesSummary']['totalLikes'] ?? 0);
            $comments = (int) ($data['commentsSummary']['totalFirstLevelComments'] ?? 0);
            $shares   = (int) ($data['shareStatistics']['shareCount'] ?? 0);

            // Impressions via statistiche ugcPost
            $statsResponse = Http::withHeaders([
                'Authorization'             => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->get("https://api.linkedin.com/v2/ugcPosts/{$encodedUrn}/statistics");

            $impressions = 0;
            $reach       = 0;

            if ($statsResponse->successful()) {
                $stats       = $statsResponse->json();
                $impressions = (int) ($stats['totalShareStatistics']['impressionCount'] ?? 0);
                $reach       = (int) ($stats['totalShareStatistics']['uniqueImpressionsCount'] ?? 0);
            }

            Log::info("[METRICS] LinkedIn: metriche raccolte per post {$ugcPostId}", [
                'impressions' => $impressions,
                'likes'       => $likes,
            ]);

            return compact('impressions', 'reach', 'likes', 'comments', 'shares');

        } catch (\Throwable $e) {
            Log::error("[METRICS] LinkedIn: eccezione per post {$ugcPostId}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── TODO: Instagram & Google Business Profile ──────────────
    // private function collectInstagramMetrics(...): ?array { /* TODO */ }
    // private function collectGbpMetrics(...): ?array { /* TODO */ }

    // ──────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Salva o aggiorna le metriche per una pubblicazione.
     */
    private function saveMetrics(PostPublication $publication, array $metrics): void
    {
        SocialMetric::updateOrCreate(
            [
                'post_publication_id'  => $publication->id,
                'metric_date'          => $publication->published_at?->toDateString() ?? now()->toDateString(),
                'metric_type'          => 'daily',
            ],
            [
                'social_connection_id' => $publication->social_connection_id,
                'impressions'          => $metrics['impressions'] ?? 0,
                'reach'                => $metrics['reach'] ?? 0,
                'engagement'           => $metrics['engagement'] ?? 0,
                'likes'                => $metrics['likes'] ?? 0,
                'comments'             => $metrics['comments'] ?? 0,
                'shares'               => $metrics['shares'] ?? 0,
                'clicks'               => $metrics['clicks'] ?? 0,
                'saves'                => $metrics['saves'] ?? 0,
                'raw_data'             => $metrics,
            ]
        );
    }

    /**
     * Controlla se il rate limit per la piattaforma è stato raggiunto.
     */
    private function isThrottled(string $platform, int $maxPerHour): bool
    {
        return RateLimiter::tooManyAttempts("metrics:{$platform}", $maxPerHour);
    }

    /**
     * Registra un tentativo nel rate limiter (finestra 1 ora).
     */
    private function hitRateLimit(string $platform): void
    {
        RateLimiter::hit("metrics:{$platform}", 3600);
    }
}
