<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Generation\Services\EditionHistoryService;
use App\Domain\Project\Contracts\ProjectServiceInterface;
use App\Domain\Project\Data\CreateProjectData;
use App\Domain\Project\Data\UpdateProjectData;
use App\Domain\Project\Enums\ProjectStatus;
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
}
