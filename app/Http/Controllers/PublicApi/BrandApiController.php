<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicApi;

use App\Domain\Brand\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET /api/v1/brands       — Lista brand dell'organizzazione.
 * GET /api/v1/brands/{id}  — Dettaglio brand.
 *
 * Corrisponde a public_api.py → /brands e /brands/{brand_id}.
 * Risposta semplificata (BrandOut).
 */
final class BrandApiController extends Controller
{
    /**
     * GET /api/v1/brands
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $brands = Brand::where('organization_id', $orgId)
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $b) => $this->toOut($b));

        return response()->json($brands->values());
    }

    /**
     * GET /api/v1/brands/{id}
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $brand = Brand::where('id', $id)
            ->where('organization_id', $request->user()->organization_id)
            ->first();

        if (! $brand) {
            return response()->json(['detail' => 'Brand non trovato'], 404);
        }

        return response()->json($this->toOut($brand));
    }

    /**
     * BrandOut — schema semplificato per Public API.
     */
    private function toOut(Brand $b): array
    {
        return [
            'id'            => $b->id,
            'name'          => $b->name,
            'sector'        => $b->sector,
            'description'   => $b->description,
            'tone_of_voice' => $b->tone_of_voice,
            'website'       => $b->website,
        ];
    }
}
