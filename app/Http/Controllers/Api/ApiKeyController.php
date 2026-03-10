<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * Gestione API Keys — corrisponde a api_keys.py (Python).
 *
 * Prefisso Python: /api/api-keys
 */
final class ApiKeyController extends Controller
{
    // GET /api/api-keys/
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $keys = ApiKey::where('organization_id', $user->organization_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiKey $k) => [
                'id'           => $k->id,
                'name'         => $k->name,
                'key_prefix'   => $k->key_prefix,
                'scopes'       => $k->scopes,
                'is_active'    => $k->is_active,
                'last_used_at' => $k->last_used_at?->toIso8601String(),
                'expires_at'   => $k->expires_at?->toIso8601String(),
                'created_at'   => $k->created_at?->toIso8601String(),
            ]);

        return response()->json($keys);
    }

    // POST /api/api-keys/
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'scopes' => 'nullable|array',
        ]);

        $rawKey    = 'nsc_' . Str::random(43);
        $keyHash   = ApiKey::hashKey($rawKey);
        $keyPrefix = substr($rawKey, 0, 12);

        $apiKey = ApiKey::create([
            'organization_id' => $request->user()->organization_id,
            'user_id'         => $request->user()->id,
            'name'            => $data['name'],
            'key_hash'        => $keyHash,
            'key_prefix'      => $keyPrefix,
            'scopes'          => $data['scopes'] ?? ['read'],
            'is_active'       => true,
        ]);

        return response()->json([
            'id'         => $apiKey->id,
            'name'       => $apiKey->name,
            'key'        => $rawKey,
            'key_prefix' => $keyPrefix,
            'scopes'     => $apiKey->scopes,
            'message'    => 'Salva questa chiave: non verrà mostrata di nuovo.',
        ], 201);
    }

    // DELETE /api/api-keys/{key_id}
    public function destroy(int $keyId, Request $request): JsonResponse
    {
        $apiKey = ApiKey::where('id', $keyId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $apiKey->delete();

        return response()->json(['success' => true, 'message' => "API Key {$keyId} eliminata"]);
    }
}
