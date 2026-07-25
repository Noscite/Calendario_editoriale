<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BrandApiKeyController extends Controller
{
    public function __construct(private readonly BrandApiKeyService $service) {}

    /**
     * GET /api/brands/{brand}/api-keys
     * Restituisce i nomi delle chiavi impostate (senza valori).
     */
    public function index(Brand $brand): JsonResponse
    {
        abort_unless($brand->organization_id === Auth::user()->organization_id, 403);

        $keyNames = \App\Domain\Brand\Models\BrandApiKey::where('brand_id', $brand->id)
            ->pluck('key_name')
            ->toArray();

        // Mappa gruppi con flag is_set per ogni chiave
        $groups = [];
        foreach (BrandApiKeyService::groups() as $label => $keys) {
            $items = [];
            foreach ($keys as $keyName => $displayName) {
                $items[] = [
                    'key'        => $keyName,
                    'label'      => $displayName,
                    'is_set'     => in_array($keyName, $keyNames, true),
                ];
            }
            $groups[] = ['label' => $label, 'keys' => $items];
        }

        return response()->json(['groups' => $groups]);
    }

    /**
     * POST /api/brands/{brand}/api-keys
     * Salva o elimina le chiavi inviate.
     * Body: { "anthropic_api_key": "sk-...", "meta_app_id": "" }
     * Chiave vuota = elimina dal DB.
     */
    public function store(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->organization_id === Auth::user()->organization_id, 403);

        $data = $request->validate([
            'keys' => ['required', 'array'],
        ])['keys'];

        try {
            $this->service->saveMany($brand, $data);
        } catch (\DomainException $e) {
            // Piano senza has_own_api_keys: la UI non mostra il form, ma una POST
            // diretta all'API arriverebbe fin qui → 403 pulito invece di 500.
            return response()->json(['detail' => $e->getMessage()], 403);
        }

        return response()->json(['message' => 'Chiavi salvate con successo']);
    }
}
