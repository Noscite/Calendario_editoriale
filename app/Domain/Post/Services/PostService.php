<?php

declare(strict_types=1);

namespace App\Domain\Post\Services;

use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Post\Contracts\PostRepositoryInterface;
use App\Domain\Post\Contracts\PostServiceInterface;
use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use App\Domain\Social\Contracts\SocialConnectionRepositoryInterface;
use App\Domain\Social\Models\PostPublication;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PostService implements PostServiceInterface
{
    /**
     * Tipi media accettati per upload.
     */
    private const array IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const array VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm'];

    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
        private readonly SocialConnectionRepositoryInterface $socialConnectionRepository,
        private readonly ContentGeneratorInterface $contentGenerator,
    ) {}

    // ------------------------------------------------------------------
    //  CRUD
    // ------------------------------------------------------------------

    public function listByProject(int $projectId): Collection
    {
        return $this->postRepository->findByProject($projectId);
    }

    public function getById(int $id): Post
    {
        return $this->postRepository->findOrFail($id);
    }

    public function create(array $data): Post
    {
        $data['status'] ??= 'draft';
        $data['publication_status'] ??= PublicationStatus::Draft->value;

        return $this->postRepository->create($data);
    }

    public function update(int $postId, array $data): Post
    {
        $post = $this->postRepository->findOrFail($postId);

        return $this->postRepository->update($post, $data);
    }

    public function delete(int $postId): void
    {
        $post = $this->postRepository->findOrFail($postId);

        $this->postRepository->delete($post);
    }

    // ------------------------------------------------------------------
    //  BATCH OPERATIONS
    // ------------------------------------------------------------------

    public function batchDelete(array $postIds): int
    {
        return $this->postRepository->deleteMany($postIds);
    }

    /**
     * Sostituisce i post selezionati con nuovi generati via AI,
     * preservando date/orari/piattaforme originali.
     */
    public function batchReplace(array $postIds, ?string $brief = null): Collection
    {
        $postsToReplace = new Collection();

        foreach ($postIds as $id) {
            $post = $this->postRepository->findById($id);
            if ($post) {
                $postsToReplace->push($post);
            }
        }

        if ($postsToReplace->isEmpty()) {
            throw ValidationException::withMessages([
                'post_ids' => ['Nessun post trovato con gli ID forniti.'],
            ]);
        }

        $firstPost = $postsToReplace->first();
        $project = $firstPost->project;

        // Determina periodo e piattaforme dai post selezionati
        $dates = $postsToReplace->pluck('scheduled_date');
        $platforms = $postsToReplace->pluck('platform')->unique()->values()->all();

        // Chiede generazione AI
        $generatedPosts = $this->contentGenerator->generateAiPosts($project->id, [
            'platforms' => $platforms,
            'start_date' => $dates->min(),
            'end_date' => $dates->max(),
            'num_posts' => $postsToReplace->count(),
            'brief' => $brief ?? '',
        ]);

        return DB::transaction(function () use ($postsToReplace, $generatedPosts, $project) {
            // Elimina vecchi
            $this->postRepository->deleteMany(
                $postsToReplace->pluck('id')->all()
            );

            // Crea nuovi preservando date/orari/piattaforme originali
            $newPosts = new Collection();
            $generatedArray = $generatedPosts->all();

            foreach ($postsToReplace->values() as $i => $original) {
                $generated = $generatedArray[$i] ?? end($generatedArray);

                $newData = [
                    'project_id' => $project->id,
                    'platform' => $original->platform,
                    'scheduled_date' => $original->scheduled_date,
                    'scheduled_time' => $original->scheduled_time,
                    'content' => $generated->content ?? '',
                    'hashtags' => $generated->hashtags ?? [],
                    'pillar' => $generated->pillar ?? '',
                    'post_type' => $generated->post_type ?? '',
                    'visual_suggestion' => $generated->visual_suggestion ?? '',
                    'cta' => $generated->cta ?? '',
                    'call_to_action' => $generated->cta ?? '',
                    'status' => 'draft',
                    // Propaga il metadata del Post AI-generato sorgente: il post
                    // ricreato qui condivide la stessa generazione, solo con
                    // date/orari/piattaforma forzati a quelli del post originale.
                    'generation_metadata' => $generated->generation_metadata ?? null,
                ];

                $newPosts->push($this->postRepository->create($newData));
            }

            return $newPosts;
        });
    }

    // ------------------------------------------------------------------
    //  AI REGENERATION
    // ------------------------------------------------------------------

    public function regenerate(int $postId, ?string $prompt = null): Post
    {
        return $this->contentGenerator->regeneratePost($postId, $prompt);
    }

    // ------------------------------------------------------------------
    //  SCHEDULING  (replica scheduler_service.py)
    // ------------------------------------------------------------------

    /**
     * Programma un post per la pubblicazione automatica.
     *
     * Per ogni piattaforma richiesta verifica che esista una connessione attiva,
     * quindi crea/aggiorna il record PostPublication con status = scheduled.
     */
    public function schedule(int $postId, \DateTimeInterface $scheduledFor, array $platforms): Post
    {
        $post = $this->postRepository->findOrFail($postId);
        $project = $post->project;
        $brandId = $project->brand_id;

        foreach ($platforms as $platform) {
            $platformEnum = \App\Domain\Post\Enums\Platform::from($platform);

            $connection = $this->socialConnectionRepository
                ->findByBrandAndPlatform($brandId, $platformEnum);

            if (! $connection) {
                throw ValidationException::withMessages([
                    'platforms' => [
                        "Nessuna connessione attiva per {$platform}. Connetti prima l'account nelle impostazioni.",
                    ],
                ]);
            }

            // Crea o aggiorna la pubblicazione
            PostPublication::updateOrCreate(
                [
                    'post_id' => $postId,
                    'social_connection_id' => $connection->id,
                ],
                [
                    'status' => 'scheduled',
                    'scheduled_for' => $scheduledFor,
                    'error_message' => null,
                    'retry_count' => 0,
                ]
            );
        }

        // Aggiorna stato post
        $this->postRepository->update($post, [
            'publication_status' => PublicationStatus::Scheduled,
        ]);

        return $post->refresh();
    }

    /**
     * Annulla la schedulazione: rimuove pubblicazioni "scheduled"
     * e riporta il post a status "draft".
     */
    public function cancelSchedule(int $postId): Post
    {
        $post = $this->postRepository->findOrFail($postId);

        PostPublication::where('post_id', $postId)
            ->where('status', 'scheduled')
            ->delete();

        $this->postRepository->update($post, [
            'publication_status' => PublicationStatus::Draft,
        ]);

        return $post->refresh();
    }

    /**
     * Restituisce lo stato di pubblicazione per piattaforma.
     *
     * Replica la logica di get_schedule_status in posts.py.
     */
    public function getScheduleStatus(int $postId): array
    {
        $post = $this->postRepository->findOrFail($postId);

        $publications = PostPublication::with('socialConnection')
            ->where('post_id', $postId)
            ->get();

        $result = $publications->map(fn (PostPublication $pub) => [
            'platform' => $pub->socialConnection?->platform ?? 'unknown',
            'status' => $pub->status,
            'scheduled_for' => $pub->scheduled_for?->toIso8601String(),
            'published_at' => $pub->published_at?->toIso8601String(),
            'external_post_url' => $pub->external_post_url,
            'error_message' => $pub->error_message,
        ]);

        return [
            'post_id' => $postId,
            'publication_status' => $post->publication_status,
            'publications' => $result->all(),
        ];
    }

    // ------------------------------------------------------------------
    //  MEDIA UPLOAD
    // ------------------------------------------------------------------

    /**
     * Carica un media (immagine o video) e lo associa al post.
     *
     * Replica upload_post_media() di posts.py.
     */
    public function uploadMedia(int $postId, UploadedFile $file): Post
    {
        $post = $this->postRepository->findOrFail($postId);

        $mime = $file->getMimeType();
        $allowedMimes = array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES);

        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => ['Tipo file non supportato. Usa JPG, PNG, WEBP, GIF per immagini o MP4, MOV, WEBM per video.'],
            ]);
        }

        $mediaType = in_array($mime, self::VIDEO_MIMES, true) ? 'video' : 'image';

        $extension = $file->getClientOriginalExtension() ?: ($mediaType === 'video' ? 'mp4' : 'jpg');
        $filename = "{$postId}_" . Str::random(8) . ".{$extension}";

        $file->storeAs('posts', $filename, 'public');

        $mediaUrl = "/storage/posts/{$filename}";

        $this->postRepository->update($post, [
            'image_url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        return $post->refresh();
    }

    // ------------------------------------------------------------------
    //  OVERLAP CHECK
    // ------------------------------------------------------------------

    /**
     * Verifica sovrapposizioni in un intervallo di date.
     *
     * Replica check_overlap() di generation.py.
     */
    public function checkOverlap(int $projectId, string $startDate, string $endDate): array
    {
        $overlapping = $this->postRepository
            ->findByProjectAndDateRange($projectId, $startDate, $endDate);

        // Raggruppa per piattaforma
        $byPlatform = $overlapping->groupBy('platform')
            ->map(fn (Collection $posts) => $posts->count())
            ->all();

        // Post fuori dal range (mantenuti)
        $totalProjectPosts = $this->postRepository->findByProject($projectId)->count();
        $keptCount = $totalProjectPosts - $overlapping->count();

        return [
            'has_overlap' => $overlapping->isNotEmpty(),
            'overlapping_count' => $overlapping->count(),
            'overlapping_by_platform' => $byPlatform,
            'kept_count' => $keptCount,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }
}
