<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Contracts\BrandServiceInterface;
use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Services\EditionHistoryService;
use App\Domain\Generation\Services\PersonasEvaluationTracker;
use App\Domain\Project\Contracts\ProjectServiceInterface;
use App\Domain\Project\Data\CreateProjectData;
use App\Domain\Project\Data\UpdateProjectData;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Jobs\EvaluateOrGeneratePersonasJob;
use App\Domain\Project\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CRUD progetti — corrisponde a projects.py (Python).
 */
final class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectServiceInterface $projectService,
    ) {}

    /**
     * Serializza un progetto nello stesso formato di project_to_dict() Python.
     */
    private function toDict(Project $p): array
    {
        $data = [
            'id'                => $p->id,
            'brand_id'          => $p->brand_id,
            'parent_project_id' => $p->parent_project_id,
            'edition_number'    => $p->edition_number,
            'name'              => $p->name,
            'description'       => $p->description ?? '',
            'start_date'        => (string) $p->start_date?->format('Y-m-d'),
            'end_date'          => (string) $p->end_date?->format('Y-m-d'),
            'platforms'         => $p->platforms ?? [],
            'posts_per_week'    => $p->posts_per_week ?? (object) [],
            'themes'            => $p->themes ?? [],
            'brief'             => $p->brief ?? '',
            'custom_prompt'     => $p->custom_prompt ?? '',
            'status'            => $p->status?->value ?? 'draft',
            'reference_urls'    => $p->reference_urls ?? [],
            'target_audience'   => $p->target_audience ?? '',
            'objectives'        => $p->objectives ?? [],
            'content_pillars'   => $p->content_pillars ?? [],
            'competitors'       => $p->competitors ?? [],
            'special_dates'     => $p->special_dates ?? [],
            'buyer_personas'    => $p->buyer_personas,
            'posts_count'       => $p->posts_count ?? $p->posts()->count(),
            'created_at'        => $p->created_at?->toIso8601String(),
        ];

        // Include editions list when loaded
        if ($p->relationLoaded('editions')) {
            $data['editions'] = $p->editions->map(fn ($e) => [
                'id'             => $e->id,
                'edition_number' => $e->edition_number,
                'name'           => $e->name,
                'start_date'     => (string) $e->start_date?->format('Y-m-d'),
                'end_date'       => (string) $e->end_date?->format('Y-m-d'),
                'status'         => $e->status?->value ?? 'draft',
                'posts_count'    => $e->posts_count ?? $e->posts()->count(),
            ])->values();
        }

        return $data;
    }

    // GET /api/projects?brand_id=&include_editions=1
    public function index(Request $request): JsonResponse
    {
        $brandId         = $request->query('brand_id');
        $includeEditions = $request->boolean('include_editions');

        $query = $brandId
            ? Project::where('brand_id', (int) $brandId)
            : Project::query();

        // By default, hide child editions from the main list
        if (! $includeEditions) {
            $query->whereNull('parent_project_id');
        }

        $projects = $query->withCount('posts')
            ->with(['editions' => fn ($q) => $q->withCount('posts')])
            ->get();

        return response()->json($projects->map(fn ($p) => $this->toDict($p))->values());
    }

    // GET /api/projects/{id}
    public function show(int $id): JsonResponse
    {
        $project = $this->projectService->getById($id);

        return response()->json($this->toDict($project));
    }

    // POST /api/projects
    public function store(Request $request): JsonResponse
    {
        $dto  = CreateProjectData::from($request);
        $data = $dto->toArray();

        $data['status'] = ProjectStatus::Draft;

        $project = $this->projectService->create($data);

        return response()->json($this->toDict($project), 201);
    }

    // PUT /api/projects/{id}
    public function update(int $id, Request $request): JsonResponse
    {
        // Normalizza le date in Y-m-d prima della validazione
        // (alcuni browser inviano formati localizzati es. "13/03/2026")
        $input = $request->all();
        foreach (['start_date', 'end_date'] as $field) {
            if (!empty($input[$field]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input[$field])) {
                try {
                    $input[$field] = \Carbon\Carbon::parse($input[$field])->format('Y-m-d');
                } catch (\Throwable) {
                    $input[$field] = null; // lascia che la validazione gestisca il null
                }
            }
        }
        $request = $request->duplicate(null, null, null, null, null, null);
        $request->replace($input);

        try {
            $dto = UpdateProjectData::from($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error("ProjectController::update validation failed", [
                'project_id' => $id,
                'errors'     => $e->errors(),
                'input'      => $input,
            ]);
            throw $e;
        }
        $data = $dto->toArray();

        // Converti status string in enum, come il Python
        if (isset($data['status'])) {
            $data['status'] = ProjectStatus::from($data['status']);
        }

        $project = $this->projectService->update($id, $data);

        return response()->json($this->toDict($project));
    }

    // DELETE /api/projects/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->projectService->delete($id);

        return response()->json(['message' => 'Project deleted successfully']);
    }

    // ─── EDITIONS ────────────────────────────────────────────

    // POST /api/projects/{id}/editions
    public function addEdition(int $id, Request $request): JsonResponse
    {
        $parent = Project::findOrFail($id);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        // If parent itself has no edition_number, make it edition 1
        if ($parent->edition_number === null && ! $parent->isEdition()) {
            $parent->update(['edition_number' => 1]);
        }

        $editionNumber = $parent->isEdition()
            ? Project::where('parent_project_id', $parent->parent_project_id)->max('edition_number') + 1
            : $parent->nextEditionNumber();

        $parentId = $parent->isEdition() ? $parent->parent_project_id : $parent->id;

        $edition = Project::create([
            'organization_id'   => $parent->organization_id,
            'brand_id'          => $parent->brand_id,
            'parent_project_id' => $parentId,
            'edition_number'    => $editionNumber,
            'name'              => $data['name'],
            'start_date'        => $data['start_date'],
            'end_date'          => $data['end_date'],
            'platforms'         => $parent->platforms,
            'posts_per_week'    => $parent->posts_per_week,
            'themes'            => $parent->themes,
            'brief'             => $parent->brief,
            'custom_prompt'     => $parent->custom_prompt,
            'status'            => ProjectStatus::Draft,
            'reference_urls'    => $parent->reference_urls,
            'target_audience'   => $parent->target_audience,
            'objectives'        => $parent->objectives,
            'content_pillars'   => $parent->content_pillars,
            'competitors'       => $parent->competitors,
            'special_dates'     => $parent->special_dates,
            'buyer_personas'    => $parent->buyer_personas,
        ]);

        return response()->json($this->toDict($edition), 201);
    }

    // GET /api/projects/{id}/history-context
    public function historyContext(int $id, EditionHistoryService $service): JsonResponse
    {
        $project = Project::findOrFail($id);
        $context = $service->buildContext($project);

        return response()->json([
            'project_id'      => $project->id,
            'edition_number'  => $project->edition_number,
            'has_history'     => $context !== '',
            'history_context' => $context,
        ]);
    }

    // ─── WIZARD PR-2: AI personas evaluation ─────────────────

    /**
     * POST /api/projects/{id}/evaluate-personas
     *
     * Dispatcha EvaluateOrGeneratePersonasJob in background.
     * Idempotente: 409 se job già in stato 'evaluating'.
     */
    public function evaluatePersonas(int $id, Request $request): JsonResponse
    {
        $project = $this->findProjectInUserOrgOrFail($id, $request);

        if (PersonasEvaluationTracker::isEvaluating($id)) {
            return response()->json([
                'status'  => 'already_evaluating',
                'message' => 'Valutazione personas già in corso per questo project.',
            ], 409);
        }

        EvaluateOrGeneratePersonasJob::dispatch($project->id);

        return response()->json([
            'status'             => PersonasEvaluationTracker::STATUS_EVALUATING,
            'estimated_seconds'  => 15,
            'message'            => 'Valutazione personas avviata.',
        ], 202);
    }

    /**
     * GET /api/projects/{id}/personas-status
     *
     * Polling: ritorna stato cache (evaluating/ready/failed) + payload completo
     * dal DB se ready (buyer_personas + personas_source + personas_ai_suggestion).
     */
    public function personasStatus(int $id, Request $request): JsonResponse
    {
        $project = $this->findProjectInUserOrgOrFail($id, $request);

        $tracker = PersonasEvaluationTracker::get($id);
        $cacheStatus = $tracker['status'] ?? null;

        // Se cache scaduta ma DB ha personas + source → consideralo ready
        $hasPersistedSuggestion = $project->personas_ai_suggestion !== null;
        $hasPersonas            = ! empty($project->buyer_personas);

        if ($cacheStatus === null && $hasPersistedSuggestion && $hasPersonas) {
            $cacheStatus = PersonasEvaluationTracker::STATUS_READY;
        }
        if ($cacheStatus === null) {
            $cacheStatus = 'idle';
        }

        return response()->json([
            'status'                 => $cacheStatus,
            'tracker'                => $tracker,
            'personas_source'        => $project->personas_source,
            'personas_ai_suggestion' => $project->personas_ai_suggestion,
            'buyer_personas'         => $project->buyer_personas,
        ]);
    }

    /**
     * POST /api/projects/{id}/force-regenerate-personas
     *
     * Dispatcha job con forceGenerateNew=true. Resetta tracker.
     */
    public function forceRegeneratePersonas(int $id, Request $request): JsonResponse
    {
        $project = $this->findProjectInUserOrgOrFail($id, $request);

        PersonasEvaluationTracker::clear($id);
        EvaluateOrGeneratePersonasJob::dispatch($project->id, forceGenerateNew: true);

        return response()->json([
            'status'            => PersonasEvaluationTracker::STATUS_EVALUATING,
            'estimated_seconds' => 12,
            'message'           => 'Rigenerazione personas avviata.',
        ], 202);
    }

    /**
     * POST /api/projects/{id}/confirm-personas
     *
     * Marca le buyer_personas come confermate (confirmed=true). Endpoint
     * dedicato wizard-v2; il legacy /api/generate/personas/{id}/confirm resta
     * per il vecchio ProjectWizard (deprecato in PR-WIZARD-3).
     */
    public function confirmPersonas(int $id, Request $request): JsonResponse
    {
        $project = $this->findProjectInUserOrgOrFail($id, $request);

        $bp = $project->buyer_personas;
        if (empty($bp) || ! is_array($bp)) {
            return response()->json([
                'status'  => 'no_personas',
                'message' => 'Nessuna persona da confermare.',
            ], 422);
        }

        $bp['confirmed']    = true;
        $bp['confirmed_at'] = now()->toIso8601String();
        $project->update(['buyer_personas' => $bp]);

        return response()->json([
            'status'  => 'confirmed',
            'message' => 'Personas confermate.',
        ]);
    }

    /**
     * POST /api/projects/{id}/promote-pillars-to-brand
     *
     * Body: { pillars: [{name, description}, ...] }.
     * Invoca BrandService::mergeDefaultPillars con la lista passata.
     */
    public function promotePillarsToBrand(int $id, Request $request, BrandServiceInterface $brandService): JsonResponse
    {
        $project = $this->findProjectInUserOrgOrFail($id, $request);
        $brand   = Brand::find($project->brand_id);

        if (! $brand || $brand->organization_id !== $request->user()->organization_id) {
            return response()->json(['detail' => 'Brand not found'], 404);
        }

        $data = $request->validate([
            'pillars'                 => 'required|array|min:1',
            'pillars.*.name'          => 'required|string|max:60',
            'pillars.*.description'   => 'nullable|string|max:200',
        ]);

        $result = $brandService->mergeDefaultPillars($brand, $data['pillars']);

        return response()->json([
            'status'             => 'ok',
            'pillars'            => $result['pillars'],
            'added_count'        => $result['added_count'],
            'dropped_count'      => $result['dropped_count'],
            'skipped_duplicates' => $result['skipped_duplicates'],
        ]);
    }

    // ─── helper privato ───────────────────────────────────────

    /**
     * Carica il project + verifica che appartenga all'organizzazione dell'utente.
     * 404 silenzioso se non trovato o other-org.
     */
    private function findProjectInUserOrgOrFail(int $id, Request $request): Project
    {
        $project = Project::find($id);
        if (! $project || $project->organization_id !== $request->user()->organization_id) {
            abort(404, 'Project not found');
        }
        return $project;
    }
}
