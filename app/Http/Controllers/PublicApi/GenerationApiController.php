<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicApi;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Jobs\GenerateCalendarJob;
use App\Domain\Project\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * POST /api/v1/generate/calendar/{project_id} — Genera calendario con AI.
 *
 * Corrisponde a public_api.py → /generate/calendar/{project_id}.
 * Auth via X-API-Key + scope "write".
 */
final class GenerationApiController extends Controller
{
    /**
     * Verifica che il progetto appartenga all'organizzazione dell'utente.
     */
    private function verifyProjectAccess(Request $request, int $projectId): ?Project
    {
        return Project::query()
            ->join('brands', 'projects.brand_id', '=', 'brands.id')
            ->where('brands.organization_id', $request->user()->organization_id)
            ->where('projects.id', $projectId)
            ->select('projects.*')
            ->first();
    }

    /**
     * POST /api/v1/generate/calendar/{project_id}
     *
     * Avvia la generazione AI del calendario editoriale per un progetto.
     * Il processo è asincrono: il progetto passa in stato 'generating'.
     */
    public function generateCalendar(int $projectId, Request $request): JsonResponse
    {
        $project = $this->verifyProjectAccess($request, $projectId);

        if (! $project) {
            return response()->json(['detail' => 'Progetto non trovato o non accessibile'], 404);
        }

        if ($project->status?->value === 'generating' || $project->getRawOriginal('status') === 'generating') {
            return response()->json(['detail' => 'Generazione già in corso'], 409);
        }

        // Aggiorna stato a "generating"
        $project->update(['status' => 'generating']);

        GenerateCalendarJob::dispatch($project->id);

        return response()->json([
            'success'    => true,
            'message'    => 'Generazione calendario avviata',
            'project_id' => $project->id,
            'status'     => 'generating',
        ]);
    }
}
