<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignGenerationService;
use App\Domain\Campaign\Support\CampaignBrandDocuments;
use App\Domain\Project\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint per gestione campagne DENTRO un calendario di project.
 *
 * Le campagne si creano sempre nel contesto di un project (entry point
 * unico = modal "Campagna AI" del calendario, mai standalone).
 */
final class ProjectCampaignController extends Controller
{
    public function __construct(private readonly CampaignGenerationService $service) {}

    /**
     * Lista delle campagne lanciate dentro questo project (storico read-only).
     */
    public function index(int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $campaignIds = $project->posts()
            ->whereNotNull('campaign_id')
            ->distinct()
            ->pluck('campaign_id');

        $campaigns = Campaign::whereIn('id', $campaignIds)
            ->withCount(['posts as posts_in_project_count' => function ($q) use ($project) {
                $q->where('project_id', $project->id);
            }])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Campaign $c) => [
                'id'                      => $c->id,
                'name'                    => $c->name,
                'brief'                   => $c->brief,
                'pillar'                  => $c->pillar,
                'status'                  => $c->status instanceof CampaignStatus ? $c->status->value : (string) $c->status,
                'status_label'            => $c->status instanceof CampaignStatus ? $c->status->label() : null,
                'posts_in_project_count'  => (int) $c->posts_in_project_count,
                'created_at'              => $c->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $campaigns]);
    }

    /**
     * Crea una Campaign + dispatcha la generation (entry point standard).
     *
     * Multipart form-data per supportare upload allegati nella stessa
     * request. mcp_servers via JSON nel campo testuale 'mcp_servers'
     * (axios serializza array nested male in multipart).
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'brief'       => 'required|string|max:5000',
            'pillar'      => 'nullable|string|max:255',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'platforms'   => 'nullable|array',
            'platforms.*' => 'string|in:linkedin,instagram,facebook,google_business',
            'posts_count' => 'nullable|integer|min:1|max:50',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:25600',
            'mcp_servers'   => 'nullable',
        ]);

        $validated['attachments'] = $request->file('attachments') ?? [];
        $validated['mcp_servers'] = $this->parseMcpServers($request->input('mcp_servers'));
        // brand_documents arriva come stringa JSON (multipart). Lo passa al
        // service che fa il sync DENTRO la transazione, prima del dispatch del
        // job di generazione (così la generation vede già i documenti).
        $validated['brand_documents'] = CampaignBrandDocuments::parse($request->input('brand_documents'));

        $campaign = $this->service->createAndGenerate(
            project: $project,
            data: $validated,
            userId: $request->user()?->id,
        );

        return response()->json([
            'id'      => $campaign->id,
            'name'    => $campaign->name,
            'status'  => $campaign->status->value,
            'message' => 'Campagna creata. Generazione post in corso. I post compariranno nel calendario quando pronti.',
        ], 201);
    }

    /**
     * Crea una Campaign in Draft (per upload pre-submit di allegati/MCP).
     * Il modal frontend la chiama lazy quando l'utente espande "📎 Documenti KB"
     * o "🔌 MCP" e non c'è ancora un campaign_id come FK target.
     */
    public function storeDraft(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $data = $request->validate([
            'name'                          => 'nullable|string|max:255',
            'brand_documents'               => 'nullable|array',
            'brand_documents.*.id'          => 'integer|exists:brand_documents,id',
            'brand_documents.*.inject_mode' => 'nullable|in:summary,full_text',
        ]);

        $campaign = Campaign::create([
            'organization_id'    => $project->organization_id,
            'name'               => $data['name'] ?? 'Bozza campagna',
            'brief'              => '',
            'status'             => CampaignStatus::Draft,
            'created_by_user_id' => $request->user()?->id,
        ]);

        $campaign->brands()->syncWithoutDetaching([$project->brand_id]);

        $this->syncBrandDocuments(
            $campaign,
            $request->has('brand_documents') ? $request->input('brand_documents', []) : null,
            $project->organization_id,
        );

        return response()->json([
            'id'     => $campaign->id,
            'status' => 'draft',
        ], 201);
    }

    /**
     * Promuove una Draft Campaign al lancio generation. Allegati e MCP sono
     * già stati caricati via gli endpoint dedicati (campaign attachments/mcp-servers)
     * mentre la Campaign era in Draft.
     */
    public function promote(Request $request, int $projectId, int $campaignId): JsonResponse
    {
        $project  = Project::findOrFail($projectId);
        $campaign = Campaign::findOrFail($campaignId);

        if ($campaign->status !== CampaignStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => "Campaign non in stato draft (stato attuale: {$campaign->status->value})",
            ]);
        }

        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'brief'                         => 'required|string|max:5000',
            'pillar'                        => 'nullable|string|max:255',
            'start_date'                    => 'nullable|date',
            'end_date'                      => 'nullable|date|after_or_equal:start_date',
            'platforms'                     => 'nullable|array',
            'platforms.*'                   => 'string|in:linkedin,instagram,facebook,google_business',
            'posts_count'                   => 'nullable|integer|min:1|max:50',
            'brand_documents'               => 'nullable|array',
            'brand_documents.*.id'          => 'integer|exists:brand_documents,id',
            'brand_documents.*.inject_mode' => 'nullable|in:summary,full_text',
        ]);

        // Sync PRIMA di promoteDraft: promoteDraft dispatcha GenerateCampaignPostsJob,
        // i documenti KB devono già essere sulla pivot quando la generation parte.
        $this->syncBrandDocuments(
            $campaign,
            $request->has('brand_documents') ? $request->input('brand_documents', []) : null,
            $campaign->organization_id,
        );

        $this->service->promoteDraft($campaign, $project, $validated);

        return response()->json([
            'id'      => $campaign->id,
            'name'    => $campaign->name,
            'status'  => $campaign->status->value,
            'message' => 'Generazione post avviata.',
        ]);
    }

    /**
     * Accetta mcp_servers come array nativo (JSON request) o stringa JSON
     * (multipart request che serializza array nested male).
     *
     * @return array<int, array{name: string, url: string, api_key?: ?string}>
     */
    private function parseMcpServers(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Sincronizza la pivot campaign_brand_document.
     * - $docs === null  => campo assente nel payload: NON tocca la selezione.
     * - $docs === []    => selezione esplicitamente svuotata.
     * - in_array guard  => inject_mode non valido da payload manipolato => 'summary'.
     * - filtro org      => Anti-IDOR: solo documenti il cui brand appartiene
     *   all'organization.
     *
     * @param  array<int, array{id:int, inject_mode?:string}>|null  $docs
     */
    private function syncBrandDocuments(Campaign $campaign, ?array $docs, int $orgId): void
    {
        CampaignBrandDocuments::sync($campaign, $docs, $orgId);
    }
}
