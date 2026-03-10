<?php

declare(strict_types=1);

namespace App\Domain\Social\Jobs;

use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Services\SocialPublishService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job per pubblicare un singolo post su una piattaforma social.
 *
 * Dispatched da:
 * - PublishScheduledPostsJob (scheduler automatico)
 * - Controller manuale (pubblicazione immediata)
 *
 * $tries=2 per gestire errori temporanei delle API social.
 * $timeout=120 per gestire upload immagini e polling reels Instagram.
 */
class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;
    public int $backoff = 30;

    public function __construct(
        private readonly int $postId,
        private readonly ?int $connectionId = null,
    ) {}

    public function handle(SocialPublishService $publishService): void
    {
        $post = Post::with('project')->find($this->postId);
        if (!$post) {
            Log::error("PublishPostJob: post not found", ['post_id' => $this->postId]);
            return;
        }

        // Trova la connessione: se specificata usa quella, altrimenti cerca automaticamente
        $connection = $this->connectionId
            ? SocialConnection::find($this->connectionId)
            : $publishService->findConnectionForPost($post);

        if (!$connection) {
            Log::warning("PublishPostJob: no active connection found", [
                'post_id' => $this->postId,
                'platform' => $post->platform->value,
            ]);

            $post->update(['publication_status' => 'failed']);
            return;
        }

        // Pubblica tramite l'orchestratore
        $result = $publishService->publishPost($post, $connection);

        Log::info("PublishPostJob completed", [
            'post_id' => $this->postId,
            'success' => $result['success'],
            'platform' => $connection->platform->value,
        ]);
    }

    /**
     * Gestisci il fallimento definitivo del job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("PublishPostJob failed permanently", [
            'post_id' => $this->postId,
            'error' => $exception->getMessage(),
        ]);

        $post = Post::find($this->postId);
        if ($post) {
            $post->update(['publication_status' => 'failed']);
        }
    }
}
