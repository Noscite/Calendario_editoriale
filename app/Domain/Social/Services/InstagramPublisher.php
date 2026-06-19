<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Publisher per Instagram.
 *
 * Replica esatta di publisher_service.py → publish_to_instagram()
 * 2-step container flow: POST media (container) → POST media_publish
 * Carousel: N children containers → parent CAROUSEL container → delay → publish
 * Reel: REELS container → polling status (30 × 5s) → publish
 */
class InstagramPublisher
{
    /** Numero massimo tentativi polling reel */
    private const REEL_POLL_MAX_ATTEMPTS = 30;

    /** Secondi tra ogni tentativo di polling */
    private const REEL_POLL_INTERVAL = 5;

    /** Delay prima della pubblicazione (secondi) */
    private const PUBLISH_DELAY = 5;

    /**
     * Pubblica un post su Instagram.
     *
     * @return array{success: bool, external_post_id?: string, external_post_url?: string, error?: string}
     */
    public function publish(Post $post, SocialConnection $connection): array
    {
        try {
            $igAccountId = $connection->external_account_id;
            $accessToken = $connection->access_token;

            // Instagram richiede un'immagine
            if (!$post->image_url && !$post->is_carousel) {
                return ['success' => false, 'error' => 'Instagram richiede un\'immagine per il post'];
            }

            // Contenuto con hashtags (doppio newline, come Python)
            $caption = $post->content ?? '';
            $hashtags = $post->hashtags ?? [];

            if (!empty($hashtags)) {
                $hashtagString = collect($hashtags)
                    ->map(fn (string $h) => str_starts_with($h, '#') ? $h : "#{$h}")
                    ->implode(' ');
                $caption .= "\n\n{$hashtagString}";
            }

            // Carousel
            if ($post->is_carousel && !empty($post->carousel_images)) {
                return $this->publishCarousel($igAccountId, $accessToken, $caption, $post->carousel_images);
            }

            // Reel/Video
            if ($post->media_type === 'video' || $post->media_type === 'reel') {
                return $this->publishReel($igAccountId, $accessToken, $caption, $post->image_url);
            }

            // Singola immagine
            return $this->publishSingleImage($igAccountId, $accessToken, $caption, $post->image_url);
        } catch (\Throwable $e) {
            Log::error("Instagram publish exception", [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pubblica singola immagine: container → delay → publish.
     */
    private function publishSingleImage(string $igAccountId, string $accessToken, string $caption, string $imageUrl): array
    {
        $absoluteUrl = $this->makeAbsoluteUrl($imageUrl);

        // Step 1: Crea container
        $containerResponse = Http::post($this->apiBase() . "/{$igAccountId}/media", [
            'image_url' => $absoluteUrl,
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);

        if (!$containerResponse->successful()) {
            $error = $containerResponse->json('error.message', $containerResponse->body());
            return ['success' => false, 'error' => "Instagram container error: {$error}"];
        }

        $containerId = $containerResponse->json('id');
        if (!$containerId) {
            return ['success' => false, 'error' => 'Instagram: no container ID returned'];
        }

        // Delay prima della pubblicazione (come Python: asyncio.sleep(5))
        sleep(self::PUBLISH_DELAY);

        // Step 2: Pubblica
        return $this->publishContainer($igAccountId, $accessToken, $containerId);
    }

    /**
     * Pubblica carousel: N children → parent container → delay → publish.
     */
    private function publishCarousel(string $igAccountId, string $accessToken, string $caption, array $imageUrls): array
    {
        $childrenIds = [];

        // Crea container per ogni immagine figlio
        foreach ($imageUrls as $imageUrl) {
            $absoluteUrl = $this->makeAbsoluteUrl($imageUrl);

            $childResponse = Http::post($this->apiBase() . "/{$igAccountId}/media", [
                'image_url' => $absoluteUrl,
                'is_carousel_item' => true,
                'access_token' => $accessToken,
            ]);

            if (!$childResponse->successful()) {
                Log::error("Instagram carousel child container failed", [
                    'url' => $absoluteUrl,
                    'error' => $childResponse->body(),
                ]);
                continue;
            }

            $childId = $childResponse->json('id');
            if ($childId) {
                $childrenIds[] = $childId;
            }
        }

        if (empty($childrenIds)) {
            return ['success' => false, 'error' => 'Failed to create any carousel child containers'];
        }

        // Crea parent container CAROUSEL
        $parentResponse = Http::post($this->apiBase() . "/{$igAccountId}/media", [
            'media_type' => 'CAROUSEL',
            'caption' => $caption,
            'children' => implode(',', $childrenIds),
            'access_token' => $accessToken,
        ]);

        if (!$parentResponse->successful()) {
            $error = $parentResponse->json('error.message', $parentResponse->body());
            return ['success' => false, 'error' => "Instagram carousel parent error: {$error}"];
        }

        $parentId = $parentResponse->json('id');
        if (!$parentId) {
            return ['success' => false, 'error' => 'Instagram: no carousel parent ID returned'];
        }

        // Delay (come Python: asyncio.sleep(5))
        sleep(self::PUBLISH_DELAY);

        return $this->publishContainer($igAccountId, $accessToken, $parentId);
    }

    /**
     * Pubblica reel/video: container → polling status → publish.
     */
    private function publishReel(string $igAccountId, string $accessToken, string $caption, string $videoUrl): array
    {
        $absoluteUrl = $this->makeAbsoluteUrl($videoUrl);

        // Step 1: Crea container REELS
        $containerResponse = Http::post($this->apiBase() . "/{$igAccountId}/media", [
            'media_type' => 'REELS',
            'video_url' => $absoluteUrl,
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);

        if (!$containerResponse->successful()) {
            $error = $containerResponse->json('error.message', $containerResponse->body());
            return ['success' => false, 'error' => "Instagram reel container error: {$error}"];
        }

        $containerId = $containerResponse->json('id');
        if (!$containerId) {
            return ['success' => false, 'error' => 'Instagram: no reel container ID returned'];
        }

        // Step 2: Polling per status FINISHED (30 tentativi × 5s, come Python)
        for ($attempt = 0; $attempt < self::REEL_POLL_MAX_ATTEMPTS; $attempt++) {
            sleep(self::REEL_POLL_INTERVAL);

            $statusResponse = Http::get($this->apiBase() . "/{$containerId}", [
                'fields' => 'status_code',
                'access_token' => $accessToken,
            ]);

            if ($statusResponse->successful()) {
                $statusCode = $statusResponse->json('status_code');
                Log::info("Instagram reel status poll", [
                    'attempt' => $attempt + 1,
                    'status' => $statusCode,
                ]);

                if ($statusCode === 'FINISHED') {
                    break;
                }

                if ($statusCode === 'ERROR') {
                    return ['success' => false, 'error' => 'Instagram reel processing failed'];
                }
            }
        }

        // Step 3: Pubblica
        return $this->publishContainer($igAccountId, $accessToken, $containerId);
    }

    /**
     * Pubblica un container (step finale comune).
     * POST /{ig_account_id}/media_publish con creation_id.
     */
    private function publishContainer(string $igAccountId, string $accessToken, string $containerId): array
    {
        $publishResponse = Http::post($this->apiBase() . "/{$igAccountId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);

        if ($publishResponse->successful()) {
            $mediaId = $publishResponse->json('id', '');
            Log::info("Instagram post published", ['media_id' => $mediaId]);

            return [
                'success' => true,
                'external_post_id' => $mediaId,
                'external_post_url' => "https://www.instagram.com/p/{$mediaId}/",
            ];
        }

        $error = $publishResponse->json('error.message', $publishResponse->body());
        return ['success' => false, 'error' => "Instagram publish error: {$error}"];
    }

    /**
     * Base URL Graph API Meta. Versione centralizzata in
     * config('services.meta.graph_version') (default v18.0).
     */
    private function apiBase(): string
    {
        return 'https://graph.facebook.com/' . config('services.meta.graph_version');
    }

    /**
     * Converti URL relativi in assoluti (replica di make_absolute_url Python).
     */
    private function makeAbsoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        $baseUrl = rtrim(config('app.url'), '/');
        return $baseUrl . '/' . ltrim($url, '/');
    }
}
