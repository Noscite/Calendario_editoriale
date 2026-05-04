<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Contracts\ImageGeneratorInterface;
use App\Domain\Generation\Data\RegeneratePersonasRequestData;
use App\Domain\Generation\Data\RegeneratePostRequestData;
use App\Domain\Generation\Jobs\GenerateCalendarJob;
use App\Domain\Generation\Services\EditionHistoryService;
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
        $project = Project::with('brand')->findOrFail($projectId);

        // Guard: se il progetto è già in generazione, non dispatcha un secondo job
        if ($project->status === ProjectStatus::Generating) {
            return response()->json([
                'status'  => 'already_generating',
                'message' => 'La generazione è già in corso. Attendi il completamento.',
            ], 409);
        }

        // Preflight: blocca il dispatch se mancano chiavi API obbligatorie sul brand.
        // Il job in background non avrebbe contesto Auth per il fallback super-admin,
        // quindi fallirebbe immediatamente con MissingBrandApiKeyException.
        if ($project->brand) {
            $missingKeys = app(BrandApiKeyService::class)->getMissingRequiredKeys($project->brand);
            if (! empty($missingKeys)) {
                return response()->json([
                    'status'       => 'missing_api_keys',
                    'message'      => 'Impossibile avviare la generazione: chiavi API mancanti sul brand "' . $project->brand->name . '".',
                    'missing_keys' => $missingKeys,
                    'brand_id'     => $project->brand_id,
                    'brand_name'   => $project->brand->name,
                ], 422);
            }
        }

        $personasStatus = 'not_generated';
        if ($project->buyer_personas) {
            $personasStatus = ($project->buyer_personas['confirmed'] ?? false)
                ? 'confirmed'
                : 'generated';
        }

        // Build edition history context if this is an edition
        $historyContext = '';
        if ($project->isEdition()) {
            $historyContext = app(EditionHistoryService::class)->buildContext($project);
        }

        $project->status = ProjectStatus::Generating;
        $project->save();

        GenerateCalendarJob::dispatch($projectId, $historyContext);

        return response()->json([
            'status'          => 'generating',
            'personas_status' => $personasStatus,
            'message'         => 'Generazione avviata',
        ]);
    }

    // GET /api/generate/preflight/{project_id}
    // Verifica se il progetto è pronto a essere generato (chiavi API presenti).
    // Permette al frontend di disabilitare il pulsante e mostrare un alert
    // PRIMA che l'utente provi a lanciare la generazione.
    public function preflight(int $projectId): JsonResponse
    {
        $project = Project::with('brand')->findOrFail($projectId);

        if (! $project->brand) {
            return response()->json([
                'can_generate' => false,
                'reason'       => 'no_brand',
                'message'      => 'Il progetto non ha un brand associato.',
                'missing_keys' => [],
            ]);
        }

        $missingKeys = app(BrandApiKeyService::class)->getMissingRequiredKeys($project->brand);

        return response()->json([
            'can_generate' => empty($missingKeys),
            'reason'       => empty($missingKeys) ? null : 'missing_api_keys',
            'missing_keys' => $missingKeys,
            'brand_id'     => $project->brand_id,
            'brand_name'   => $project->brand->name,
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

    // GET /api/generate/preview/{project_id}
    public function preview(int $projectId): JsonResponse
    {
        $project = Project::with('brand')->findOrFail($projectId);
        $brand = $project->brand;

        $startDate = $project->start_date;
        $endDate = $project->end_date;
        $weeks = max(1, (int) ceil($startDate->diffInDays($endDate) / 7));

        $platforms = $project->platforms ?? [];
        $postsPerWeek = $project->posts_per_week ?? [];

        $platformPostTypes = [
            'instagram' => ['feed', 'carousel', 'reel'],
            'linkedin' => ['article', 'update'],
            'facebook' => ['post', 'link', 'video'],
            'google_business' => ['update', 'offer'],
        ];

        $defaultTimes = [
            'instagram' => ['09:00', '12:00', '18:00'],
            'linkedin' => ['08:00', '12:00', '17:00'],
            'facebook' => ['09:00', '13:00', '19:00'],
            'google_business' => ['10:00', '14:00'],
        ];

        $personasTimes = [];
        $personas = $project->buyer_personas;
        if ($personas && !empty($personas['scheduling_strategy'])) {
            foreach ($personas['scheduling_strategy'] as $platform => $strategy) {
                if (!empty($strategy['optimal_slots'])) {
                    $personasTimes[$platform] = array_values(array_unique(
                        array_map(fn ($s) => $s['time'] ?? '09:00', array_slice($strategy['optimal_slots'], 0, 3))
                    ));
                }
            }
        }

        $totalPosts = 0;
        $platformDetails = [];
        foreach ($platforms as $platform) {
            $ppw = $postsPerWeek[$platform] ?? 3;
            $platformTotal = $weeks * $ppw;
            $totalPosts += $platformTotal;

            $platformDetails[] = [
                'platform' => $platform,
                'posts_per_week' => $ppw,
                'total_posts' => $platformTotal,
                'post_types' => $platformPostTypes[$platform] ?? ['post'],
                'optimal_times' => $personasTimes[$platform] ?? $defaultTimes[$platform] ?? ['09:00'],
            ];
        }

        $pillars = $project->content_pillars ?? [];
        if (!empty($pillars)) {
            $share = (int) floor(100 / count($pillars));
            $remainder = 100 - ($share * count($pillars));
            $distribution = [];
            foreach (array_values($pillars) as $i => $pillar) {
                $distribution[] = ['pillar' => $pillar, 'percentage' => $share + ($i === 0 ? $remainder : 0)];
            }
        } else {
            $distribution = [
                ['pillar' => 'Educational',  'percentage' => 40],
                ['pillar' => 'Promotional',  'percentage' => 25],
                ['pillar' => 'Engagement',   'percentage' => 35],
            ];
        }

        $personasList = [];
        $personasCount = 0;
        if ($personas && !empty($personas['personas'])) {
            $personasCount = count($personas['personas']);
            foreach ($personas['personas'] as $p) {
                $personasList[] = [
                    'name' => $p['name'] ?? 'Senza nome',
                    'role' => $p['demographics']['role'] ?? $p['role'] ?? '',
                    'platforms' => $p['preferred_platforms'] ?? $platforms,
                ];
            }
        }

        $documentsCount = \App\Domain\Document\Models\BrandDocument::where('brand_id', $brand->id)->count();

        $editionContext = [
            'is_edition' => $project->isEdition(),
            'previous_editions' => $project->isEdition()
                ? ($project->parentProject?->editions()->where('id', '<', $project->id)->count() ?? 0)
                : 0,
            'topics_to_avoid' => [],
        ];

        $estimatedTokens = $totalPosts * 800;
        $estimatedMinutes = max(1, (int) ceil($totalPosts / 30));

        $warnings = [];
        if ($personasCount === 0) {
            $warnings[] = 'Nessuna buyer persona confermata: gli orari di pubblicazione saranno generici';
        }
        if ($documentsCount === 0) {
            $warnings[] = 'Nessun documento caricato: i contenuti saranno basati solo sul brief';
        }
        if ($weeks > 13) {
            $warnings[] = "Periodo molto lungo ({$weeks} settimane): la generazione potrebbe richiedere 5+ minuti";
        }
        if ($totalPosts > 200) {
            $warnings[] = "Molti post da generare ({$totalPosts}): considera di ridurre il periodo o la frequenza";
        }

        $briefSummary = $project->brief
            ? (mb_strlen($project->brief) > 200 ? mb_substr($project->brief, 0, 200) . '...' : $project->brief)
            : null;

        return response()->json([
            'project_name' => $project->name,
            'brand_name' => $brand->name,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'weeks' => $weeks,
            ],
            'total_posts' => $totalPosts,
            'platforms' => $platformDetails,
            'content_distribution' => $distribution,
            'themes' => $project->themes ?? [],
            'personas_count' => $personasCount,
            'personas' => $personasList,
            'brief_summary' => $briefSummary,
            'has_documents' => $documentsCount > 0,
            'documents_count' => $documentsCount,
            'edition_context' => $editionContext,
            'estimated_tokens' => $estimatedTokens,
            'estimated_duration_minutes' => $estimatedMinutes,
            'warnings' => $warnings,
        ]);
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
