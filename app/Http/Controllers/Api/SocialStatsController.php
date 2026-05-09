<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Models\Post;
use App\Domain\Social\Jobs\CollectSocialMetricsJob;
use App\Domain\Social\Models\PostPublication;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Models\SocialMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Social stats — corrisponde a social_stats.py (Python).
 *
 * Prefisso Python: /api/social/stats
 */
final class SocialStatsController extends Controller
{
    // GET /api/social/stats/brand/{brand_id}?days=30
    public function brandStats(int $brandId, Request $request): JsonResponse
    {
        // Brand ha BelongsToOrganization → 404 automatico se non è dell'org dell'utente
        $brand = Brand::findOrFail($brandId);

        $days        = max(1, (int) $request->query('days', 30));
        $periodStart = now()->subDays($days)->startOfDay();
        $periodEnd   = now()->endOfDay();

        // Connessioni attive con la loro ultima metrica account-level
        $connections = SocialConnection::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->get();

        $platforms = $connections->map(function ($conn) use ($periodStart, $periodEnd) {
            // Aggrega le metriche per-post nel periodo (CollectSocialMetricsJob
            // scrive sempre con post_publication_id valorizzato — non esistono
            // metriche account-level nel writer attuale, quindi sommare le
            // per-post è la sola strategia che usa i dati realmente raccolti).
            // Il filtro è su metric_date (= published_at del post) perché
            // l'utente vuole "post pubblicati negli ultimi N giorni", non
            // "metriche fetchate negli ultimi N giorni".
            $aggregate = SocialMetric::where('social_connection_id', $conn->id)
                ->whereNotNull('post_publication_id')
                ->whereBetween('metric_date', [$periodStart, $periodEnd])
                ->selectRaw('
                    COALESCE(SUM(impressions), 0) AS impressions,
                    COALESCE(SUM(reach), 0)        AS reach,
                    COALESCE(SUM(engagement), 0)   AS engagement,
                    COALESCE(SUM(likes), 0)        AS likes,
                    COALESCE(SUM(comments), 0)     AS comments,
                    COALESCE(SUM(shares), 0)       AS shares,
                    COALESCE(SUM(clicks), 0)       AS clicks,
                    MAX(fetched_at)                AS last_fetched_at
                ')
                ->first();

            // followers_count: campo account-level, non popolato dal job
            // CollectSocialMetricsJob attuale. Tentiamo l'ultima metric
            // account-level se per caso esiste (forward-compat). Altrimenti null.
            $accountMetric = SocialMetric::where('social_connection_id', $conn->id)
                ->whereNull('post_publication_id')
                ->orderByDesc('fetched_at')
                ->first();

            return [
                'platform'        => $conn->platform->value ?? $conn->platform,
                'account_name'    => $conn->external_account_name ?? '',
                'impressions'     => (int) ($aggregate->impressions ?? 0),
                'reach'           => (int) ($aggregate->reach ?? 0),
                'engagement'      => (int) ($aggregate->engagement ?? 0),
                'likes'           => (int) ($aggregate->likes ?? 0),
                'comments'        => (int) ($aggregate->comments ?? 0),
                'shares'          => (int) ($aggregate->shares ?? 0),
                'clicks'          => (int) ($aggregate->clicks ?? 0),
                'followers_count' => $accountMetric?->followers_count,
                'fetched_at'      => $aggregate?->last_fetched_at,
            ];
        })->values();

        // Post pubblicati nel periodo (via project → brand)
        $projectIds = $brand->projects()->pluck('id');
        $totalPosts = Post::whereIn('project_id', $projectIds)->count();
        $publishedPosts = PostPublication::whereIn(
            'post_id',
            Post::whereIn('project_id', $projectIds)->pluck('id')
        )
            ->where('status', 'published')
            ->whereBetween('published_at', [$periodStart, $periodEnd])
            ->count();

        // Engagement rate aggregato
        $totalReach      = $platforms->sum('reach');
        $totalEngagement = $platforms->sum('engagement');
        $engagementRate  = $totalReach > 0
            ? round(($totalEngagement / $totalReach) * 100, 2)
            : 0;

        return response()->json([
            'brand_id'        => $brand->id,
            'period_start'    => $periodStart->toIso8601String(),
            'period_end'      => $periodEnd->toIso8601String(),
            'platforms'       => $platforms,
            'engagement_rate' => $engagementRate,
            'published_posts' => $publishedPosts,
            'total_posts'     => $totalPosts,
        ]);
    }

    // POST /api/social/stats/fetch/{brand_id}
    public function fetchStats(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::findOrFail($brandId);

        $connections = SocialConnection::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->get();

        if ($connections->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nessuna connessione social attiva per questo brand',
            ], 404);
        }

        foreach ($connections as $connection) {
            CollectSocialMetricsJob::dispatch($connection->id);
        }

        return response()->json([
            'success'    => true,
            'message'    => "Raccolta metriche avviata per {$connections->count()} connessioni",
            'brand_id'   => $brand->id,
            'dispatched' => $connections->count(),
        ]);
    }

    // GET /api/social/stats/post/{publication_id}
    public function postStats(int $publicationId, Request $request): JsonResponse
    {
        $publication = PostPublication::with('metrics', 'socialConnection', 'post')
            ->findOrFail($publicationId);

        // Verifica ownership tramite post → organizzazione (Post ha BelongsToOrganization)
        Post::findOrFail($publication->post_id);

        $metric = $publication->metrics->sortByDesc('fetched_at')->first();

        return response()->json([
            'publication_id' => $publicationId,
            'post_id'        => $publication->post_id,
            'platform'       => $publication->socialConnection?->platform?->value,
            'status'         => $publication->status,
            'published_at'   => $publication->published_at?->toIso8601String(),
            'external_url'   => $publication->external_post_url,
            'metrics'        => $metric ? [
                'impressions' => $metric->impressions,
                'reach'       => $metric->reach,
                'engagement'  => $metric->engagement,
                'likes'       => $metric->likes,
                'comments'    => $metric->comments,
                'shares'      => $metric->shares,
                'clicks'      => $metric->clicks,
                'saves'       => $metric->saves,
                'fetched_at'  => $metric->fetched_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
