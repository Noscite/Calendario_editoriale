<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicApi;

use App\Domain\Brand\Models\Brand;
use App\Domain\Project\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET /api/v1/context — contesto utente: brands, progetti attivi.
 * GET /api/v1/me     — info utente e API key.
 *
 * Corrisponde a public_api.py → /context e /me.
 */
final class ContextController extends Controller
{
    /**
     * GET /api/v1/me
     *
     * Info sull'utente e organizzazione associati alla API key.
     */
    public function me(Request $request): JsonResponse
    {
        $user   = $request->user();
        $apiKey = $request->attributes->get('api_key');

        return response()->json([
            'user_id'         => $user->id,
            'email'           => $user->email,
            'full_name'       => $user->full_name,
            'organization_id' => $user->organization_id,
            'api_key_name'    => $apiKey->name,
            'scopes'          => $apiKey->scopes,
            'key_prefix'      => $apiKey->key_prefix,
        ]);
    }

    /**
     * GET /api/v1/context
     *
     * Contesto completo: brands e progetti attivi.
     * Se c'è un solo brand/progetto, vengono indicati come default.
     */
    public function context(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $brands = Brand::where('organization_id', $orgId)->get();

        $result = $brands->map(function (Brand $brand) {
            $projects = Project::where('brand_id', $brand->id)
                ->whereIn('status', ['review', 'approved', 'published'])
                ->orderByDesc('start_date')
                ->get();

            return [
                'brand_id'   => $brand->id,
                'brand_name' => $brand->name,
                'sector'     => $brand->sector,
                'projects'   => $projects->map(fn (Project $p) => [
                    'project_id' => $p->id,
                    'name'       => $p->name,
                    'start_date' => $p->start_date?->format('Y-m-d'),
                    'end_date'   => $p->end_date?->format('Y-m-d'),
                    'platforms'  => $p->platforms,
                    'status'     => $p->status?->value ?? $p->getRawOriginal('status'),
                ])->values()->all(),
            ];
        })->values()->all();

        // Identifica default se c'è un solo brand/progetto
        $defaultBrand   = count($result) === 1 ? $result[0] : null;
        $defaultProject = null;

        if ($defaultBrand && count($defaultBrand['projects']) === 1) {
            $defaultProject = $defaultBrand['projects'][0];
        }

        return response()->json([
            'brands'               => $result,
            'default_brand_id'     => $defaultBrand['brand_id'] ?? null,
            'default_brand_name'   => $defaultBrand['brand_name'] ?? null,
            'default_project_id'   => $defaultProject['project_id'] ?? null,
            'default_project_name' => $defaultProject['name'] ?? null,
            'hint'                 => 'Usa default_brand_id e default_project_id per creare post se disponibili',
        ]);
    }
}
