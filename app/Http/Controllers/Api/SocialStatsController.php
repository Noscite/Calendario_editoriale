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
    // GET /api/social/stats/brand/{brand_id}
    public function brandStats(int $brandId, Request $request): JsonResponse
    {
        // Brand ha BelongsToOrganization → 404 automatico se non è dell'org dell'utente
        $brand = Brand::findOrFail($brandId);

        $connections = SocialConnection::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->pluck('id');

        $metrics = SocialMetric::whereIn('social_connection_id', $connections)
            ->whereNull('post_publication_id')
            ->orderByDesc('fetched_at')
            ->get()
            ->groupBy('social_connection_id')
            ->map(fn ($rows) => $rows->first());

        $stats = $metrics->map(fn ($m) => [
            'connection_id'   => $m->social_connection_id,
            'impressions'     => $m->impressions,
            'reach'           => $m->reach,
            'engagement'      => $m->engagement,
            'likes'           => $m->likes,
            'comments'        => $m->comments,
            'shares'          => $m->shares,
            'clicks'          => $m->clicks,
            'followers_count' => $m->followers_count,
            'fetched_at'      => $m->fetched_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'brand_id' => $brand->id,
            'metrics'  => $stats,
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
