<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Contracts\ImageGeneratorInterface;
use App\Domain\Generation\Data\RegeneratePersonasRequestData;
use App\Domain\Generation\Data\RegeneratePostRequestData;
use App\Domain\Generation\Jobs\GenerateCalendarJob;
use App\Domain\Generation\Services\GenerationTracker;
use App\Domain\Post\Contracts\PostServiceInterface;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Generazione calendario editoriale — corrisponde a generation.py (Python).
 *
 * Step 1: Genera/Rigenera/Conferma buyer personas
 * Step 2: Genera calendario (background job)
 * Step 3: Polling stato generazione
 */
final class GenerationController extends Controller
{
    public function __construct(
        private readonly PostServiceInterface $postService,
        private readonly ContentGeneratorInterface $contentGenerator,
        private readonly ImageGeneratorInterface $imageGenerator,
    ) {}

    // ─── STEP 1: BUYER PERSONAS ────────────────────────────────

    // POST /api/generate/personas/{project_id}
    public function generatePersonas(int $projectId, Request $request): JsonResponse
    {
        $personas = $this->contentGenerator->generatePersonas($projectId);

        return response()->json([
            'status'   => 'generated',
            'personas' => $personas,
            'message'  => 'Buyer personas generate. Rivedi e conferma per procedere.',
        ]);
    }

    // POST /api/generate/personas/{project_id}/regenerate
    public function regeneratePersonas(int $projectId, Request $request): JsonResponse
    {
        $dto = RegeneratePersonasRequestData::from($request);

        $personas = $this->contentGenerator->regeneratePersonas($projectId, $dto->feedback);

        return response()->json([
            'status'   => 'regenerated',
            'personas' => $personas,
            'message'  => 'Personas rigenerate con le tue indicazioni.',
        ]);
    }

    // PUT|POST /api/generate/personas/{project_id}/confirm
    public function confirmPersonas(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        // Se l'utente ha passato personas modificate, usale
        $personas = $request->input('personas');
        if ($personas) {
            $project->buyer_personas = $personas;
        }

        // Marca come confermate
        if ($project->buyer_personas) {
            $bp = $project->buyer_personas;
            $bp['confirmed']    = true;
            $bp['confirmed_at'] = now()->toIso8601String();
            $project->buyer_personas = $bp;
        }

        $project->save();

        return response()->json([
            'status'  => 'confirmed',
            'message' => 'Personas confermate. Ora puoi generare il calendario.',
        ]);
    }

    // GET /api/generate/personas/{project_id}
    public function getPersonas(int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        return response()->json([
            'personas'  => $project->buyer_personas,
            'confirmed' => $project->buyer_personas['confirmed'] ?? false,
        ]);
    }

    // ─── STEP 2: GENERA CALENDARIO ────────────────────────────

    // POST /api/generate/calendar/{project_id}
    public function generateCalendar(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $personasStatus = 'not_generated';
        if ($project->buyer_personas) {
            $personasStatus = ($project->buyer_personas['confirmed'] ?? false)
                ? 'confirmed'
                : 'generated';
        }

        $project->status = ProjectStatus::Generating;
        $project->save();

        GenerateCalendarJob::dispatch($projectId);

        return response()->json([
            'status'          => 'generating',
            'personas_status' => $personasStatus,
            'message'         => 'Generazione avviata',
        ]);
    }

    // ─── STEP 3: STATUS POLLING ────────────────────────────────

    // GET /api/generate/status/{project_id}
    public function status(int $projectId): JsonResponse
    {
        $project   = Project::findOrFail($projectId);
        $postCount = Post::where('project_id', $projectId)->count();

        $percent      = 0;
        $currentBatch = 0;
        $totalBatches = 0;

        if ($project->status === ProjectStatus::Generating) {
            $cached = GenerationTracker::get($projectId);
            if ($cached) {
                $percent      = $cached['percent'] ?? 0;
                $currentBatch = $cached['current_batch'] ?? 0;
                $totalBatches = $cached['total_batches'] ?? 0;
            } else {
                $totalDays    = max(1, $project->start_date->diffInDays($project->end_date) + 1);
                $totalBatches = (int) ceil($totalDays / 7);
            }
        } elseif ($project->status === ProjectStatus::Review) {
            $percent = 100;
        }

        return response()->json([
            'status'        => $project->status?->value ?? 'draft',
            'post_count'    => $postCount,
            'percent'       => $percent,
            'current_batch' => $currentBatch,
            'total_batches' => $totalBatches,
        ]);
    }

    // ─── SINGOLO POST ──────────────────────────────────────────

    // POST /api/generate/regenerate-post/{post_id}
    public function regeneratePost(int $postId, Request $request): JsonResponse
    {
        $dto = RegeneratePostRequestData::from($request);

        $post = $this->postService->regenerate($postId, $dto->user_prompt);

        return response()->json([
            'id'                => $post->id,
            'content'           => $post->content,
            'hashtags'          => $post->hashtags,
            'visual_suggestion' => $post->visual_suggestion,
            'cta'               => $post->cta,
        ]);
    }

    // POST /api/generate/image-prompt/{post_id}
    public function imagePrompt(int $postId): JsonResponse
    {
        $imagePrompt = $this->imageGenerator->generateImagePrompt($postId);

        return response()->json(['image_prompt' => $imagePrompt]);
    }

    // ─── GESTIONE SINGOLE PERSONAS ─────────────────────────────

    // POST /api/generate/personas/{project_id}/add
    public function addPersona(int $projectId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'persona_description' => 'nullable|string',
        ]);

        $result = $this->contentGenerator->addPersona($projectId, $data['persona_description'] ?? null);

        return response()->json($result);
    }

    // DELETE /api/generate/personas/{project_id}/{index}
    public function deletePersona(int $projectId, int $index): JsonResponse
    {
        try {
            $result = $this->contentGenerator->deletePersona($projectId, $index);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['detail' => $e->getMessage()], 404);
        }

        return response()->json($result);
    }

    // POST /api/generate/personas/{project_id}/{index}/regenerate
    public function regenerateSinglePersona(int $projectId, int $index, Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'nullable|string',
        ]);

        try {
            $result = $this->contentGenerator->regenerateSinglePersona($projectId, $index, $data['description'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['detail' => $e->getMessage()], 404);
        }

        return response()->json($result);
    }

    // POST /api/generate/check-overlap/{project_id}
    public function checkOverlap(int $projectId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        $project = Project::findOrFail($projectId);

        $startDate = $data['start_date'];
        $endDate   = $data['end_date'];

        $overlapping = Post::where('project_id', $projectId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->get();

        $byPlatform = $overlapping->groupBy(fn ($p) => $p->getRawOriginal('platform') ?? 'altro')
            ->map->count();

        $kept = Post::where('project_id', $projectId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('scheduled_date', '<', $startDate)
                  ->orWhere('scheduled_date', '>', $endDate);
            })->count();

        return response()->json([
            'has_overlap'              => $overlapping->isNotEmpty(),
            'overlapping_count'        => $overlapping->count(),
            'overlapping_by_platform'  => $byPlatform,
            'kept_count'               => $kept,
            'date_range'               => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
            'current_project_dates'    => [
                'start' => $project->start_date?->format('Y-m-d'),
                'end'   => $project->end_date?->format('Y-m-d'),
            ],
        ]);
    }

    // POST /api/generate/ — endpoint root per generazione (legacy)
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        return $this->generateCalendar((int) $data['project_id'], $request);
    }
}
