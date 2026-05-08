<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Contracts\BrandServiceInterface;
use App\Domain\Brand\Data\CreateBrandData;
use App\Domain\Brand\Data\UpdateBrandData;
use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandCompletenessService;
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
    //
    // Ritorna il payload completo del Brand: tutti i campi necessari al wizard
    // PR-1 per riprendere l'edit (campi nuovi: tagline/founder/narrative_assets/
    // default_content_pillars/forbidden_topics) + voice_examples / unique_selling_points
    // / *_url social / auto_reply_* (in precedenza non esposti — fix critico per
    // /brand/:id/wizard ripartire da DB).
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
            'id'                                 => $brand->id,
            'name'                               => $brand->name,
            'description'                        => $brand->description,
            'sector'                             => $brand->sector,
            'target_audience'                    => $brand->target_audience,
            'tone_of_voice'                      => $brand->tone_of_voice,
            'brand_values'                       => $brand->brand_values,
            'unique_selling_points'              => $brand->unique_selling_points,
            'voice_examples'                     => $brand->voice_examples,
            'style_guide'                        => $brand->style_guide,
            'colors'                             => $brand->colors,
            'website'                            => $brand->website,
            'website_url'                        => $brand->website_url,
            'linkedin_url'                       => $brand->linkedin_url,
            'instagram_url'                      => $brand->instagram_url,
            'facebook_url'                       => $brand->facebook_url,
            // ── Wizard PR-1 ──────────────────────────────────────────
            'tagline'                            => $brand->tagline,
            'founder'                            => $brand->founder,
            'narrative_assets'                   => $brand->narrative_assets,
            'default_content_pillars'            => $brand->default_content_pillars,
            'forbidden_topics'                   => $brand->forbidden_topics,
            // ── Auto-reply (M4) ──────────────────────────────────────
            'auto_reply_enabled'                 => $brand->auto_reply_enabled,
            'auto_reply_min_rating'              => $brand->auto_reply_min_rating,
            'auto_reply_only_positive_sentiment' => $brand->auto_reply_only_positive_sentiment,
            'auto_reply_tone'                    => $brand->auto_reply_tone,
            'auto_reply_review_mode'             => $brand->auto_reply_review_mode,
            'auto_reply_delay_minutes'           => $brand->auto_reply_delay_minutes,
            // API keys flag (computato come in legacy)
            'has_own_api_keys'                   => (bool) ($plan?->has_own_api_keys || $request->user()->role === 'superuser'),
        ]);
    }

    // GET /api/brands/{brand}/completeness
    //
    // Ritorna lo score di completezza (0-100) + breakdown per sezione + lista
    // dei campi mancanti. Soglia 70% per attivare la generazione AI (blocco
    // selettivo arriverà in PR-WIZARD-3, qui solo lettura).
    //
    // Org scoping automatico: route usa {brand} model binding + scopeBindings()
    // → il global scope BelongsToOrganization filtra per org dell'utente,
    // 404 se brand di altra org.
    public function completeness(Brand $brand, BrandCompletenessService $service): JsonResponse
    {
        return response()->json($service->score($brand));
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
