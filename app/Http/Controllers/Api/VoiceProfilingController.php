<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Voice Profiling — corrisponde a voice_profiling.py (Python).
 *
 * Prefisso Python: /api/voice-profiling
 * Tutti gli endpoint sono PUBBLICI nel backend Python.
 */
final class VoiceProfilingController extends Controller
{
    // GET /api/voice-profiling/check
    public function check(): JsonResponse
    {
        return response()->json([
            'status'    => 'available',
            'service'   => 'voice-profiling',
        ]);
    }

    // POST /api/voice-profiling/brand/{brand_id}/init
    public function init(int $brandId, Request $request): JsonResponse
    {
        // TODO: inizializzare sessione di voice profiling
        return response()->json([
            'brand_id' => $brandId,
            'session'  => 'initialized',
        ]);
    }

    // GET /api/voice-profiling/brand/{brand_id}/profile
    public function profile(int $brandId): JsonResponse
    {
        // TODO: restituire profilo voce del brand
        return response()->json([
            'brand_id' => $brandId,
            'profile'  => null,
        ]);
    }

    // POST /api/voice-profiling/brand/{brand_id}/save
    public function save(int $brandId, Request $request): JsonResponse
    {
        // TODO: salvare profilo voce del brand
        return response()->json([
            'brand_id' => $brandId,
            'saved'    => true,
        ]);
    }
}
