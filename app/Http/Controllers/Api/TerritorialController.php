<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Project\Models\Project;
use App\Domain\Territorial\Jobs\GenerateTerritorialPostsJob;
use App\Domain\Territorial\Jobs\SyncTerritorialEventsJob;
use App\Domain\Territorial\Models\TerritorialEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class TerritorialController extends Controller
{
    /** POST /api/projects/{project_id}/territorial/generate */
    public function generateForProject(int $projectId): JsonResponse
    {
        $project = Project::with('brand')->findOrFail($projectId);

        if (! $project->brand || ! in_array($project->brand->vertical ?? null, ['pro_loco', 'unpli_regional'], true)) {
            return response()->json([
                'status'  => 'not_eligible',
                'message' => 'Il brand non è configurato per la pipeline territoriale (vertical mancante).',
            ], 422);
        }

        GenerateTerritorialPostsJob::dispatch($project->id);

        return response()->json([
            'status'     => 'dispatched',
            'project_id' => $project->id,
            'message'    => 'Generazione post territoriali avviata.',
        ]);
    }

    /** POST /api/territorial/sync */
    public function syncNow(): JsonResponse
    {
        SyncTerritorialEventsJob::dispatch();

        return response()->json([
            'status'  => 'dispatched',
            'message' => 'Sync eventi territoriali avviato.',
        ]);
    }

    /** GET /api/territorial/events?status=active&limit=50 */
    public function listEvents(): JsonResponse
    {
        $events = TerritorialEvent::where('status', request()->query('status', 'active'))
            ->orderBy('start_at')
            ->limit((int) request()->query('limit', 50))
            ->get(['id', 'source', 'external_id', 'title', 'city', 'province', 'start_at', 'image_path', 'status']);

        return response()->json(['data' => $events]);
    }
}
