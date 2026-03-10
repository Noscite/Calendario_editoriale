<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicApi;

use App\Domain\Brand\Models\Brand;
use App\Domain\Project\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET /api/v1/projects       — Lista progetti (filtrabile per brand_id, status).
 * GET /api/v1/projects/{id}  — Dettaglio progetto.
 *
 * Corrisponde a public_api.py → /projects e /projects/{project_id}.
 * Risposta semplificata (ProjectOut).
 */
final class ProjectApiController extends Controller
{
    /**
     * GET /api/v1/projects
     *
     * Query params: brand_id, status
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $query = Project::query()
            ->join('brands', 'projects.brand_id', '=', 'brands.id')
            ->where('brands.organization_id', $orgId)
            ->select('projects.*');

        if ($brandId = $request->query('brand_id')) {
            $query->where('projects.brand_id', (int) $brandId);
        }

        if ($status = $request->query('status')) {
            $query->where('projects.status', $status);
        }

        $projects = $query->orderByDesc('projects.start_date')
            ->get()
            ->map(fn (Project $p) => $this->toOut($p));

        return response()->json($projects->values());
    }

    /**
     * GET /api/v1/projects/{id}
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $project = Project::query()
            ->join('brands', 'projects.brand_id', '=', 'brands.id')
            ->where('brands.organization_id', $request->user()->organization_id)
            ->where('projects.id', $id)
            ->select('projects.*')
            ->first();

        if (! $project) {
            return response()->json(['detail' => 'Progetto non trovato'], 404);
        }

        return response()->json($this->toOut($project));
    }

    /**
     * ProjectOut — schema semplificato per Public API.
     */
    private function toOut(Project $p): array
    {
        return [
            'id'             => $p->id,
            'brand_id'       => $p->brand_id,
            'name'           => $p->name,
            'start_date'     => $p->start_date?->format('Y-m-d'),
            'end_date'       => $p->end_date?->format('Y-m-d'),
            'platforms'      => $p->platforms,
            'posts_per_week' => $p->posts_per_week,
            'status'         => $p->status?->value ?? $p->getRawOriginal('status'),
        ];
    }
}
