<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Notification\Models\Notification;
use App\Domain\Post\Enums\Platform;
use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use App\Domain\Social\Exceptions\TokenRefreshFailedException;
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
 *
 * Dalla FASE 1.3: chiama TokenRefreshService prima di ogni pubblicazione.
 */
class SocialPublishService
{
    public function __construct(
        private readonly LinkedInPublisher $linkedInPublisher,
        private readonly FacebookPublisher $facebookPublisher,
        private readonly InstagramPublisher $instagramPublisher,
        private readonly GoogleBusinessPublisher $googleBusinessPublisher,
        private readonly TokenRefreshService $tokenRefreshService,
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
            'post_id'  => $post->id,
            'platform' => $connection->platform->value,
        ]);

        // Aggiorna il token se in scadenza (FASE 1.3)
        try {
            $connection = $this->tokenRefreshService->refreshIfNeeded($connection);
        } catch (TokenRefreshFailedException $e) {
            Log::error("Token refresh fallito prima della pubblicazione", [
                'post_id'  => $post->id,
                'platform' => $connection->platform->value,
                'error'    => $e->getMessage(),
            ]);

            $post->update([
                'publication_status' => PublicationStatus::Failed,
                'error_message'      => $e->getMessage(),
            ]);

            $this->notifyTokenFailure($post, $connection, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Route al publisher specifico (come Python: publish_post)
        $result = match ($connection->platform) {
            Platform::LinkedIn       => $this->linkedInPublisher->publish($post, $connection),
            Platform::Facebook       => $this->facebookPublisher->publish($post, $connection),
            Platform::Instagram      => $this->instagramPublisher->publish($post, $connection),
            Platform::GoogleBusiness => $this->googleBusinessPublisher->publish($post, $connection),
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
                    'post_id'             => $post->id,
                    'social_connection_id' => $connection->id,
                ],
                [
                    'status'           => 'published',
                    'published_at'     => now(),
                    'external_post_id' => $result['external_post_id'] ?? null,
                    'external_post_url' => $result['external_post_url'] ?? null,
                    'error_message'    => null,
                ]
            );

            Log::info("Post published successfully", [
                'post_id'      => $post->id,
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
                    'post_id'             => $post->id,
                    'social_connection_id' => $connection->id,
                ],
                [
                    'status'        => 'failed',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ]
            );

            Log::error("Post publish failed", [
                'post_id' => $post->id,
                'error'   => $result['error'] ?? 'Unknown error',
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

    /**
     * Notifica l'utente in caso di fallimento del refresh del token.
     */
    private function notifyTokenFailure(Post $post, SocialConnection $connection, string $error): void
    {
        try {
            $project = $post->project;
            if (!$project) {
                return;
            }

            // Recupera l'utente che ha collegato la connessione
            $userId = $connection->connected_by_user_id;
            if (!$userId) {
                return;
            }

            Notification::create([
                'user_id'    => $userId,
                'type'       => 'token_expired',
                'title'      => 'Token scaduto — ' . $connection->platform->label(),
                'message'    => "Il token OAuth per {$connection->external_account_name} è scaduto. "
                    . "Ricollega l'account per riprendere la pubblicazione automatica.",
                'post_id'    => $post->id,
                'project_id' => $project->id,
                'brand_id'   => $project->brand_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Impossibile creare notifica token failure", ['error' => $e->getMessage()]);
        }
    }
}
