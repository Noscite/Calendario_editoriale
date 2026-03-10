<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

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
        return [
            'id'               => $p->id,
            'brand_id'         => $p->brand_id,
            'name'             => $p->name,
            'start_date'       => (string) $p->start_date?->format('Y-m-d'),
            'end_date'         => (string) $p->end_date?->format('Y-m-d'),
            'platforms'        => $p->platforms ?? [],
            'posts_per_week'   => $p->posts_per_week ?? (object) [],
            'themes'           => $p->themes ?? [],
            'brief'            => $p->brief ?? '',
            'custom_prompt'    => $p->custom_prompt ?? '',
            'status'           => $p->status?->value ?? 'draft',
            'reference_urls'   => $p->reference_urls ?? [],
            'target_audience'  => $p->target_audience ?? '',
            'content_pillars'  => $p->content_pillars ?? [],
            'competitors'      => $p->competitors ?? [],
            'special_dates'    => $p->special_dates ?? [],
            'buyer_personas'   => $p->buyer_personas,
        ];
    }

    // GET /api/projects?brand_id=
    public function index(Request $request): JsonResponse
    {
        $brandId = $request->query('brand_id');

        if ($brandId) {
            $projects = $this->projectService->listByBrand((int) $brandId);
        } else {
            // Tutti i progetti dell'organizzazione (via BelongsToOrganization scope)
            $projects = Project::all();
        }

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
        $dto  = UpdateProjectData::from($request);
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
}
