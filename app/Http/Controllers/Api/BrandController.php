<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Contracts\BrandServiceInterface;
use App\Domain\Brand\Data\CreateBrandData;
use App\Domain\Brand\Data\UpdateBrandData;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CRUD brand — corrisponde a brands.py (Python).
 */
final class BrandController extends Controller
{
    public function __construct(
        private readonly BrandServiceInterface $brandService,
    ) {}

    // GET /api/brands
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        if (! $orgId) {
            return response()->json([]);
        }

        $brands = $this->brandService->listForOrganization($orgId);

        $result = $brands->map(function ($b) {
            return [
                'id'              => $b->id,
                'name'            => $b->name,
                'sector'          => $b->sector,
                'tone_of_voice'   => $b->tone_of_voice,
                'brand_values'    => $b->brand_values,
                'description'     => $b->description,
                'target_audience' => $b->target_audience,
                'colors'          => $b->colors,
                'style_guide'     => $b->style_guide,
                'website'         => $b->website ?? null,
                'projects_count'  => $b->projects_count ?? $b->projects()->count(),
                'posts_count'     => $b->posts_count ?? $b->projects()->withCount('posts')->get()->sum('posts_count'),
            ];
        });

        return response()->json($result);
    }

    // GET /api/brands/{id}
    public function show(int $id, Request $request): JsonResponse
    {
        $brand = $this->brandService->getById($id);

        // Org scoping (sicurezza)
        if ($brand->organization_id !== $request->user()->organization_id) {
            return response()->json(['detail' => 'Brand not found'], 404);
        }

        $org  = Organization::find($brand->organization_id);
        $plan = $org?->plan_id ? Plan::find($org->plan_id) : null;

        return response()->json([
            'id'                => $brand->id,
            'name'              => $brand->name,
            'sector'            => $brand->sector,
            'tone_of_voice'     => $brand->tone_of_voice,
            'brand_values'      => $brand->brand_values,
            'description'       => $brand->description,
            'target_audience'   => $brand->target_audience,
            'colors'            => $brand->colors,
            'style_guide'       => $brand->style_guide,
            'has_own_api_keys'  => (bool) ($plan?->has_own_api_keys || $request->user()->role === 'superuser'),
        ]);
    }

    // POST /api/brands
    public function store(Request $request): JsonResponse
    {
        $dto = CreateBrandData::from($request);

        $orgId = $request->user()->organization_id;

        if (! $orgId) {
            return response()->json(['detail' => 'User has no organization'], 400);
        }

        $brand = $this->brandService->create($orgId, $dto->toArray());

        return response()->json([
            'id'   => $brand->id,
            'name' => $brand->name,
        ], 201);
    }

    // PUT /api/brands/{id}
    public function update(int $id, Request $request): JsonResponse
    {
        $dto = UpdateBrandData::from($request);

        $brand = $this->brandService->getById($id);

        if ($brand->organization_id !== $request->user()->organization_id) {
            return response()->json(['detail' => 'Brand not found'], 404);
        }

        $data = $dto->toArray();

        // M4 — auto-reply: blocca attivazione se il piano non include la feature
        if (($data['auto_reply_enabled'] ?? null) === true) {
            $org  = $request->user()->organization;
            $plan = $org?->plan_id ? \App\Domain\Subscription\Models\Plan::find($org->plan_id) : null;
            $cap  = $plan?->monthly_reply_count;

            // null = illimitato → OK; >0 → OK; 0 = feature disabilitata; null plan → blocca
            if ($plan === null || $cap === 0) {
                return response()->json([
                    'error'   => 'feature_unavailable',
                    'message' => 'Funzione non disponibile sul tuo piano.',
                ], 422);
            }
        }

        $brand = $this->brandService->update($id, $data);

        return response()->json([
            'id'   => $brand->id,
            'name' => $brand->name,
        ]);
    }

    // DELETE /api/brands/{id}
    public function destroy(int $id, Request $request): JsonResponse
    {
        $brand = $this->brandService->getById($id);

        if ($brand->organization_id !== $request->user()->organization_id) {
            return response()->json(['detail' => 'Brand not found'], 404);
        }

        $this->brandService->delete($id);

        return response()->json(['message' => 'Brand deleted']);
    }
}
