<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

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
        // TODO: implementare aggregazione metriche social per brand
        return response()->json([
            'brand_id' => $brandId,
            'metrics'  => [],
        ]);
    }

    // POST /api/social/stats/fetch/{brand_id}
    public function fetchStats(int $brandId, Request $request): JsonResponse
    {
        // TODO: implementare fetch metriche dai provider social
        return response()->json([
            'success' => true,
            'message' => "Fetch stats avviato per brand {$brandId}",
        ]);
    }

    // GET /api/social/stats/post/{publication_id}
    public function postStats(int $publicationId, Request $request): JsonResponse
    {
        // TODO: implementare metriche singola pubblicazione
        return response()->json([
            'publication_id' => $publicationId,
            'metrics'        => [],
        ]);
    }
}
