<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Post\Enums\Platform;
use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\PostPublication;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Log;

/**
 * Orchestratore per la pubblicazione social.
 *
 * Replica esatta di publisher_service.py → publish_post()
 * e scheduler_service.py → publish_single_post()
 *
 * Ruota la chiamata al publisher specifico della piattaforma,
 * aggiorna lo stato del post e crea/aggiorna il record PostPublication.
 */
class SocialPublishService
{
    public function __construct(
        private readonly LinkedInPublisher $linkedInPublisher,
        private readonly FacebookPublisher $facebookPublisher,
        private readonly InstagramPublisher $instagramPublisher,
    ) {}

    /**
     * Pubblica un post su una piattaforma.
     * Replica esatta di publish_post() + publish_single_post().
     *
     * @return array{success: bool, external_post_id?: string, external_post_url?: string, error?: string}
     */
    public function publishPost(Post $post, SocialConnection $connection): array
    {
        Log::info("Publishing post", [
            'post_id' => $post->id,
            'platform' => $connection->platform->value,
        ]);

        // Route al publisher specifico (come Python: publish_post)
        $result = match ($connection->platform) {
            Platform::LinkedIn => $this->linkedInPublisher->publish($post, $connection),
            Platform::Facebook => $this->facebookPublisher->publish($post, $connection),
            Platform::Instagram => $this->instagramPublisher->publish($post, $connection),
            default => ['success' => false, 'error' => "Piattaforma non supportata: {$connection->platform->value}"],
        };

        // Aggiorna stato e crea/aggiorna PostPublication (come Python: publish_single_post)
        $this->updatePublicationStatus($post, $connection, $result);

        // Aggiorna last_used_at sulla connessione (come Python)
        $connection->update(['last_used_at' => now()]);

        return $result;
    }

    /**
     * Aggiorna lo stato del post e crea/aggiorna il record PostPublication.
     * Replica esatta della logica in scheduler_service.py → publish_single_post().
     */
    private function updatePublicationStatus(Post $post, SocialConnection $connection, array $result): void
    {
        if ($result['success']) {
            // Successo: aggiorna stato post
            $post->update([
                'publication_status' => PublicationStatus::Published,
            ]);

            // Crea o aggiorna record PostPublication (upsert come Python)
            PostPublication::updateOrCreate(
                [
                    'post_id' => $post->id,
                    'social_connection_id' => $connection->id,
                ],
                [
                    'status' => 'published',
                    'published_at' => now(),
                    'external_post_id' => $result['external_post_id'] ?? null,
                    'external_post_url' => $result['external_post_url'] ?? null,
                    'error_message' => null,
                ]
            );

            Log::info("Post published successfully", [
                'post_id' => $post->id,
                'external_url' => $result['external_post_url'] ?? null,
            ]);
        } else {
            // Fallimento: aggiorna stato post
            $post->update([
                'publication_status' => PublicationStatus::Failed,
            ]);

            // Crea o aggiorna record PostPublication con errore (come Python)
            PostPublication::updateOrCreate(
                [
                    'post_id' => $post->id,
                    'social_connection_id' => $connection->id,
                ],
                [
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ]
            );

            Log::error("Post publish failed", [
                'post_id' => $post->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        }
    }

    /**
     * Cerca la connessione social attiva per un post.
     * Utility usata da PublishPostJob e PublishScheduledPostsJob.
     */
    public function findConnectionForPost(Post $post): ?SocialConnection
    {
        $project = $post->project;
        if (!$project) {
            Log::error("Project not found for post", ['post_id' => $post->id]);
            return null;
        }

        return SocialConnection::where('brand_id', $project->brand_id)
            ->where('platform', $post->platform)
            ->where('is_active', true)
            ->first();
    }
}
