<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Contracts\ImageGeneratorInterface;
use App\Domain\Post\Contracts\PostServiceInterface;
use App\Domain\Post\Data\CreatePostData;
use App\Domain\Post\Data\UpdatePostData;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use App\Domain\Brand\Models\Brand;
use App\Domain\Social\Models\PostPublication;
use App\Domain\Social\Models\SocialConnection;
use App\Http\Controllers\Api\GenerationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Post CRUD + scheduling + media upload + AI generation — corrisponde a posts.py (Python).
 */
final class PostController extends Controller
{
    public function __construct(
        private readonly PostServiceInterface $postService,
        private readonly ContentGeneratorInterface $contentGenerator,
        private readonly ImageGeneratorInterface $imageGenerator,
    ) {}

    /**
     * Serializza un Post nella stessa shape di PostResponse (Python).
     */
    private function toResponse(Post $p): array
    {
        return [
            'id'                 => $p->id,
            'project_id'         => $p->project_id,
            'platform'           => $p->platform?->value ?? $p->getRawOriginal('platform'),
            'scheduled_date'     => $p->scheduled_date?->format('Y-m-d'),
            'scheduled_time'     => $p->scheduled_time,
            'title'              => $p->title,
            'content'            => $p->content,
            'hashtags'           => $p->hashtags,
            'visual_prompt'      => $p->visual_prompt,
            'visual_suggestion'  => $p->visual_suggestion,
            'pillar'             => $p->pillar,
            'post_type'          => $p->post_type?->value ?? $p->getRawOriginal('post_type'),
            'cta'                => $p->cta,
            'status'             => $p->status?->value ?? $p->getRawOriginal('status') ?? 'draft',
            'image_url'          => $p->image_url,
            'media_type'         => $p->media_type,
            'image_format'       => $p->image_format,
            'carousel_images'    => $p->carousel_images,
            'carousel_prompts'   => $p->carousel_prompts,
            'is_carousel'        => $p->is_carousel ?? false,
            'content_type'       => $p->content_type?->value ?? $p->getRawOriginal('content_type'),
            'publication_status' => $p->publication_status?->value ?? $p->getRawOriginal('publication_status'),
            'call_to_action'     => $p->call_to_action,
            'created_at'         => $p->created_at?->toIso8601String(),
        ];
    }

    // GET /api/posts/project/{project_id}
    public function byProject(int $projectId): JsonResponse
    {
        // Project ha BelongsToOrganization → 404 se non appartiene all'org dell'utente
        \App\Domain\Project\Models\Project::findOrFail($projectId);

        $posts = $this->postService->listByProject($projectId);

        return response()->json($posts->map(fn ($p) => $this->toResponse($p))->values());
    }

    // GET /api/posts/{id}
    public function show(int $id): JsonResponse
    {
        $post = $this->postService->getById($id);

        return response()->json($this->toResponse($post));
    }

    // PUT /api/posts/{id}
    public function update(int $id, Request $request): JsonResponse
    {
        $dto = UpdatePostData::from($request);

        $post = $this->postService->update($id, $dto->toArray());

        return response()->json($this->toResponse($post));
    }

    // PATCH /api/posts/{id} — alias per PUT (come il Python)
    public function patch(int $id, Request $request): JsonResponse
    {
        return $this->update($id, $request);
    }

    // DELETE /api/posts/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->postService->delete($id);

        return response()->json(['message' => 'Post eliminato']);
    }

    // POST /api/posts/manual
    public function storeManual(Request $request): JsonResponse
    {
        $dto  = CreatePostData::from($request);
        $data = $dto->toArray();

        $data['status'] = 'draft';

        $post = $this->postService->create($data);

        return response()->json($this->toResponse($post), 201);
    }

    // POST /api/posts/generate-ai
    public function generateAi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'          => 'required|integer|exists:projects,id',
            'platforms'           => 'nullable|array',
            'ai_decide_platforms' => 'nullable|boolean',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date',
            'num_posts'           => 'nullable|integer',
            'ai_decide_num_posts' => 'nullable|boolean',
            'brief'               => 'required|string',
            'pillar'              => 'nullable|string',
        ]);

        // Delega al service (che chiamerà il ContentGenerator AI)
        $project = Project::findOrFail($data['project_id']);
        $brand   = Brand::findOrFail($project->brand_id);

        // Piattaforme
        $available = $project->platforms ?? ['instagram', 'facebook', 'linkedin'];
        $platforms = ($data['ai_decide_platforms'] ?? false) || empty($data['platforms'])
            ? $available
            : $data['platforms'];

        // Numero post
        $days  = max(1, now()->parse($data['start_date'])->diffInDays(now()->parse($data['end_date'])) + 1);
        $weeks = max(1, $days / 7);

        if (($data['ai_decide_num_posts'] ?? false) || ! isset($data['num_posts'])) {
            $numPosts = (int) min(50, max(3, $weeks * count($platforms) * 3));
        } else {
            $numPosts = $data['num_posts'];
        }

        $posts = $this->contentGenerator->generateAiPosts($data['project_id'], $data);

        return response()->json($posts->map(fn ($p) => $this->toResponse($p))->values(), 201);
    }

    // POST /api/posts/batch-delete
    public function batchDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_ids'   => 'required|array',
            'post_ids.*' => 'integer',
        ]);

        $deleted = $this->postService->batchDelete($data['post_ids']);

        return response()->json([
            'message'       => "Eliminati {$deleted} post",
            'deleted_count' => $deleted,
        ]);
    }

    // POST /api/posts/batch-replace
    public function batchReplace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_ids'   => 'required|array',
            'post_ids.*' => 'integer',
            'brief'      => 'required|string',
        ]);

        $posts = $this->postService->batchReplace($data['post_ids'], $data['brief']);

        return response()->json($posts->map(fn ($p) => $this->toResponse($p))->values());
    }

    // POST /api/posts/{id}/regenerate
    public function regenerate(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => 'nullable|string',
        ]);

        $prompt = $data['prompt']
            ?? 'Rigenera questo post mantenendo lo stesso messaggio ma migliorando engagement e chiarezza';

        $post = $this->postService->regenerate($id, $prompt);

        return response()->json($this->toResponse($post));
    }

    // POST /api/posts/{id}/generate-image
    public function generateImage(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'visual_suggestion' => 'nullable|string',
        ]);

        $post = $this->postService->getById($id);

        $visualPrompt = $data['visual_suggestion'] ?? $post->visual_suggestion;

        if (! $visualPrompt) {
            return response()->json([
                'detail' => 'Nessun suggerimento visual disponibile. Inserisci un prompt per generare l\'immagine.',
            ], 400);
        }

        // Aggiorna visual_suggestion se fornito
        if (isset($data['visual_suggestion'])) {
            $post->visual_suggestion = $data['visual_suggestion'];
            $post->save();
        }

        try {
            $imageUrl = $this->imageGenerator->generateImage($id, $data['visual_suggestion'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['detail' => $e->getMessage()], 400);
        }

        return response()->json(['image_url' => $imageUrl]);
    }

    // ── Scheduling ─────────────────────────────────────────────

    // POST /api/posts/{id}/schedule
    public function schedule(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'scheduled_for' => 'required|date',
            'platforms'     => 'required|array',
            'platforms.*'   => 'string',
        ]);

        $post = $this->postService->getById($id);
        $project = $post->project;
        $brand   = Brand::findOrFail($project->brand_id);

        $scheduledPlatforms = [];

        foreach ($data['platforms'] as $platform) {
            $connection = SocialConnection::where('brand_id', $brand->id)
                ->where('platform', $platform)
                ->where('is_active', true)
                ->first();

            if (! $connection) {
                return response()->json([
                    'detail' => "Nessuna connessione attiva per {$platform}. Connetti prima l'account nelle impostazioni.",
                ], 400);
            }

            $existing = PostPublication::where('post_id', $id)
                ->where('social_connection_id', $connection->id)
                ->first();

            if ($existing) {
                $existing->update([
                    'scheduled_for' => $data['scheduled_for'],
                    'status'        => 'scheduled',
                    'error_message' => null,
                    'retry_count'   => 0,
                ]);
            } else {
                PostPublication::create([
                    'post_id'              => $id,
                    'social_connection_id' => $connection->id,
                    'status'               => 'scheduled',
                    'scheduled_for'        => $data['scheduled_for'],
                ]);
            }

            $scheduledPlatforms[] = $platform;
        }

        $post->publication_status = 'scheduled';
        $post->save();

        return response()->json([
            'message'       => 'Post pianificato con successo',
            'scheduled_for' => $data['scheduled_for'],
            'platforms'     => $scheduledPlatforms,
        ]);
    }

    // DELETE /api/posts/{id}/schedule
    public function cancelSchedule(int $id): JsonResponse
    {
        $post = $this->postService->getById($id);

        PostPublication::where('post_id', $id)
            ->where('status', 'scheduled')
            ->delete();

        $post->publication_status = 'draft';
        $post->save();

        return response()->json(['message' => 'Schedulazione annullata']);
    }

    // GET /api/posts/{id}/schedule-status
    public function scheduleStatus(int $id): JsonResponse
    {
        $post         = $this->postService->getById($id);
        $publications = PostPublication::where('post_id', $id)->get();

        $result = $publications->map(function ($pub) {
            $conn = SocialConnection::find($pub->social_connection_id);

            return [
                'platform'          => $conn?->platform ?? 'unknown',
                'status'            => $pub->status,
                'scheduled_for'     => $pub->scheduled_for?->toIso8601String(),
                'published_at'      => $pub->published_at?->toIso8601String(),
                'external_post_url' => $pub->external_post_url,
                'error_message'     => $pub->error_message,
            ];
        });

        return response()->json([
            'post_id'            => $id,
            'publication_status' => $post->publication_status?->value ?? $post->getRawOriginal('publication_status'),
            'publications'       => $result,
        ]);
    }

    // ── Media upload ───────────────────────────────────────────

    // POST /api/posts/{id}/upload-media
    public function uploadMedia(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
        ]);

        $post = $this->postService->getById($id);
        $file = $request->file('file');

        $imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $videoTypes = ['video/mp4', 'video/quicktime', 'video/webm'];
        $allowed    = array_merge($imageTypes, $videoTypes);

        if (! in_array($file->getMimeType(), $allowed)) {
            return response()->json([
                'detail' => 'Tipo file non supportato. Usa JPG, PNG, WEBP, GIF per immagini o MP4, MOV, WEBM per video.',
            ], 400);
        }

        $mediaType = in_array($file->getMimeType(), $videoTypes) ? 'video' : 'image';
        $ext       = $file->getClientOriginalExtension() ?: ($mediaType === 'video' ? 'mp4' : 'jpg');
        $filename  = "{$id}_" . Str::random(8) . ".{$ext}";

        $file->storeAs('posts', $filename, 'public');
        $mediaUrl = "/storage/posts/{$filename}";

        $post->image_url  = $mediaUrl;
        $post->media_type = $mediaType;
        $post->save();

        return response()->json([
            'media_url'  => $mediaUrl,
            'media_type' => $mediaType,
        ]);
    }

    // POST /api/posts/{id}/upload-image (retrocompatibile)
    public function uploadImage(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,webp,gif|max:10240',
        ]);

        $post = $this->postService->getById($id);
        $file = $request->file('file');

        $ext      = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = "{$id}_" . Str::random(8) . ".{$ext}";

        $file->storeAs('posts', $filename, 'public');
        $imageUrl = "/storage/posts/{$filename}";

        $post->image_url  = $imageUrl;
        $post->media_type = 'image';
        $post->save();

        return response()->json(['image_url' => $imageUrl]);
    }

    // POST /api/posts/{id}/generate-carousel
    public function generateCarousel(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'visual_suggestion' => 'required|string',
            'image_format'      => 'nullable|string',
            'is_carousel'       => 'nullable|boolean',
            'num_slides'        => 'nullable|integer|min:1|max:5',
        ]);

        $post = $this->postService->getById($id);

        $result = $this->imageGenerator->generateCarouselImages($id, $data);

        if (empty($result['images'])) {
            return response()->json(['detail' => 'Generazione immagine fallita. Riprova tra qualche secondo.'], 500);
        }

        return response()->json($result);
    }

    // POST /api/posts/check-overlap/{project_id}
    public function checkOverlap(int $projectId, Request $request): JsonResponse
    {
        // Delega a GenerationController@checkOverlap (stessa logica Python)
        return app(GenerationController::class)->checkOverlap($projectId, $request);
    }

    // POST /api/posts/{id}/publish
    public function publish(int $id, Request $request): JsonResponse
    {
        $post = $this->postService->getById($id);

        $data = $request->validate([
            'connection_id' => 'nullable|integer',
        ]);

        $post->update([
            'publication_status' => 'pending',
            'scheduled_for'      => now(),
        ]);

        \App\Domain\Social\Jobs\PublishPostJob::dispatch(
            $id,
            $data['connection_id'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Pubblicazione avviata',
            'post_id' => $id,
            'status'  => 'pending',
        ]);
    }
}
