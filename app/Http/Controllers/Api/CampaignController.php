<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Campaign\Data\CreateCampaignData;
use App\Domain\Campaign\Data\UpdateCampaignData;
use App\Domain\Campaign\Exceptions\CampaignInvalidTransitionException;
use App\Domain\Campaign\Exceptions\CampaignLimitExceededException;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Support\CampaignBrandDocuments;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

final class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $campaigns = Campaign::query()
            ->where('organization_id', $orgId)
            ->with(['brands:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Campaign $c) => $this->toListPayload($c));

        return response()->json(['data' => $campaigns]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $campaign = Campaign::with(['brands:id,name'])->find($id);

        if (! $campaign || $campaign->organization_id !== $request->user()->organization_id) {
            return response()->json(['detail' => 'Campaign not found'], 404);
        }

        return response()->json($this->toDetailPayload($campaign));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->brandDocumentsRules());

        $dto = CreateCampaignData::from($request);

        try {
            $campaign = DB::transaction(function () use ($dto, $request) {
                $campaign = Campaign::create([
                    'organization_id' => $request->user()->organization_id,
                    'name'            => $dto->name,
                    'description'     => $this->unwrapOptional($dto->description),
                    'brief'           => $this->unwrapOptional($dto->brief),
                    'start_date'      => $this->unwrapOptional($dto->start_date),
                    'end_date'        => $this->unwrapOptional($dto->end_date),
                    'status'          => 'draft',
                ]);

                if (! ($dto->brand_ids instanceof Optional) && is_array($dto->brand_ids)) {
                    $validBrandIds = $this->filterBrandsForOrganization(
                        $dto->brand_ids,
                        $request->user()->organization_id
                    );
                    $campaign->brands()->sync($validBrandIds);
                }

                $this->syncBrandDocuments($campaign, $dto->brand_documents, $request->user()->organization_id);

                return $campaign->fresh(['brands:id,name']);
            });

            return response()->json($this->toDetailPayload($campaign), 201);
        } catch (CampaignLimitExceededException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'CAMPAIGN_LIMIT_REACHED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $campaign = Campaign::find($id);

        if (! $campaign || $campaign->organization_id !== $request->user()->organization_id) {
            return response()->json(['detail' => 'Campaign not found'], 404);
        }

        $request->validate($this->brandDocumentsRules());

        $dto = UpdateCampaignData::from($request);

        // Costruisci array senza i campi Optional non passati nel payload
        $data = [];
        foreach (['name', 'description', 'brief', 'start_date', 'end_date', 'status'] as $field) {
            if (! ($dto->{$field} instanceof Optional)) {
                $data[$field] = $dto->{$field};
            }
        }

        $brandIds = ! ($dto->brand_ids instanceof Optional) ? $dto->brand_ids : null;

        try {
            $campaign = DB::transaction(function () use ($campaign, $data, $brandIds, $dto, $request) {
                if (! empty($data)) {
                    $campaign->update($data);
                }

                if (is_array($brandIds)) {
                    $validBrandIds = $this->filterBrandsForOrganization(
                        $brandIds,
                        $request->user()->organization_id
                    );
                    $campaign->brands()->sync($validBrandIds);
                }

                $this->syncBrandDocuments($campaign, $dto->brand_documents, $request->user()->organization_id);

                return $campaign->fresh(['brands:id,name']);
            });

            return response()->json($this->toDetailPayload($campaign));
        } catch (CampaignInvalidTransitionException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_STATE_TRANSITION',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (CampaignLimitExceededException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'CAMPAIGN_LIMIT_REACHED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $campaign = Campaign::find($id);

        if (! $campaign || $campaign->organization_id !== $request->user()->organization_id) {
            return response()->json(['detail' => 'Campaign not found'], 404);
        }

        $statusValue = $campaign->status instanceof \BackedEnum ? $campaign->status->value : $campaign->status;
        if (! in_array($statusValue, ['draft', 'archived'], true)) {
            return response()->json([
                'error' => [
                    'code'    => 'CAMPAIGN_NOT_DELETABLE',
                    'message' => 'Solo le campagne in stato Bozza o Archiviata possono essere eliminate.',
                ],
            ], 422);
        }

        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted']);
    }

    /**
     * Filtra i brand_ids tenendo solo quelli che appartengono effettivamente
     * all'organization dell'utente. Anti-IDOR.
     */
    private function filterBrandsForOrganization(array $brandIds, int $orgId): array
    {
        return Brand::withoutGlobalScope('organization')
            ->whereIn('id', $brandIds)
            ->where('organization_id', $orgId)
            ->pluck('id')
            ->all();
    }

    private function unwrapOptional(mixed $value): mixed
    {
        return $value instanceof Optional ? null : $value;
    }

    /**
     * Regole di validazione per la selezione di documenti KB sulla campagna.
     * Nullable: il campo può mancare nel payload (pattern fix wizard 422).
     *
     * @return array<string, string>
     */
    private function brandDocumentsRules(): array
    {
        return [
            'brand_documents'               => 'nullable|array',
            'brand_documents.*.id'          => 'integer|exists:brand_documents,id',
            'brand_documents.*.inject_mode' => 'nullable|in:summary,full_text',
        ];
    }

    /**
     * Sincronizza la pivot campaign_brand_document.
     * - $docs === null  => campo assente nel payload: NON tocca la selezione.
     * - $docs === []    => selezione esplicitamente svuotata.
     * - in_array guard  => inject_mode non valido da payload manipolato => 'summary'.
     * - filtro org      => Anti-IDOR: tiene solo i documenti il cui brand
     *   appartiene all'organization (coerente con filterBrandsForOrganization).
     *
     * @param  array<int, array{id:int, inject_mode?:string}>|null  $docs
     */
    private function syncBrandDocuments(Campaign $campaign, ?array $docs, int $orgId): void
    {
        CampaignBrandDocuments::sync($campaign, $docs, $orgId);
    }

    private function toListPayload(Campaign $c): array
    {
        $statusValue = $c->status instanceof \BackedEnum ? $c->status->value : $c->status;

        return [
            'id'          => $c->id,
            'name'        => $c->name,
            'description' => $c->description,
            'status'      => $statusValue,
            'start_date'  => $c->start_date?->toDateString(),
            'end_date'    => $c->end_date?->toDateString(),
            'brands'      => $c->brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
            'updated_at'  => $c->updated_at?->toIso8601String(),
        ];
    }

    private function toDetailPayload(Campaign $c): array
    {
        $statusValue = $c->status instanceof \BackedEnum ? $c->status->value : $c->status;

        return [
            'id'              => $c->id,
            'organization_id' => $c->organization_id,
            'name'            => $c->name,
            'description'     => $c->description,
            'brief'           => $c->brief,
            'status'          => $statusValue,
            'start_date'      => $c->start_date?->toDateString(),
            'end_date'        => $c->end_date?->toDateString(),
            'brands'          => $c->brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
            'created_at'      => $c->created_at?->toIso8601String(),
            'updated_at'      => $c->updated_at?->toIso8601String(),
        ];
    }
}
