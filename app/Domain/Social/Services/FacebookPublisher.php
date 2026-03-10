<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Publisher per Facebook.
 *
 * Replica esatta di publisher_service.py → publish_to_facebook()
 * Supporta: carousel (upload N unpublished photos → feed con attached_media),
 *           singola immagine (photos endpoint),
 *           solo testo (feed endpoint).
 */
class FacebookPublisher
{
    private const API_BASE = 'https://graph.facebook.com/v18.0';

    /**
     * Pubblica un post su Facebook.
     *
     * @return array{success: bool, external_post_id?: string, external_post_url?: string, error?: string}
     */
    public function publish(Post $post, SocialConnection $connection): array
    {
        try {
            $pageId = $connection->external_account_id;
            $accessToken = $connection->access_token;

            // Contenuto con hashtags (newline singolo, come Python)
            $content = $post->content ?? '';
            $hashtags = $post->hashtags ?? [];

            if (!empty($hashtags)) {
                $hashtagString = collect($hashtags)
                    ->map(fn (string $h) => str_starts_with($h, '#') ? $h : "#{$h}")
                    ->implode(' ');
                $content .= "\n{$hashtagString}";
            }

            // Carousel
            if ($post->is_carousel && !empty($post->carousel_images)) {
                return $this->publishCarousel($pageId, $accessToken, $content, $post->carousel_images);
            }

            // Singola immagine
            if ($post->image_url) {
                return $this->publishWithImage($pageId, $accessToken, $content, $post->image_url);
            }

            // Solo testo
            return $this->publishTextOnly($pageId, $accessToken, $content);
        } catch (\Throwable $e) {
            Log::error("Facebook publish exception", [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pubblica un carousel su Facebook.
     * Upload N foto unpublished → crea post con attached_media.
     */
    private function publishCarousel(string $pageId, string $accessToken, string $content, array $imageUrls): array
    {
        $photoIds = [];

        foreach ($imageUrls as $imageUrl) {
            $absoluteUrl = $this->makeAbsoluteUrl($imageUrl);

            $response = Http::post(self::API_BASE . "/{$pageId}/photos", [
                'url' => $absoluteUrl,
                'published' => false,
                'access_token' => $accessToken,
            ]);

            if (!$response->successful()) {
                Log::error("Facebook carousel photo upload failed", [
                    'url' => $absoluteUrl,
                    'error' => $response->body(),
                ]);
                continue;
            }

            $photoId = $response->json('id');
            if ($photoId) {
                $photoIds[] = $photoId;
            }
        }

        if (empty($photoIds)) {
            return ['success' => false, 'error' => 'Failed to upload any carousel images'];
        }

        // Crea post con attached_media
        $attachedMedia = collect($photoIds)
            ->map(fn (string $id) => ['media_fbid' => $id])
            ->values()
            ->all();

        $postData = [
            'message' => $content,
            'attached_media' => $attachedMedia,
            'access_token' => $accessToken,
        ];

        $response = Http::post(self::API_BASE . "/{$pageId}/feed", $postData);

        if ($response->successful()) {
            $postId = $response->json('id', '');
            Log::info("Facebook carousel published", ['post_id' => $postId]);

            return [
                'success' => true,
                'external_post_id' => $postId,
                'external_post_url' => "https://facebook.com/{$postId}",
            ];
        }

        $error = $response->json('error.message', $response->body());
        return ['success' => false, 'error' => "Facebook carousel error: {$error}"];
    }

    /**
     * Pubblica con singola immagine (endpoint photos).
     */
    private function publishWithImage(string $pageId, string $accessToken, string $content, string $imageUrl): array
    {
        $absoluteUrl = $this->makeAbsoluteUrl($imageUrl);

        $response = Http::post(self::API_BASE . "/{$pageId}/photos", [
            'caption' => $content,
            'url' => $absoluteUrl,
            'access_token' => $accessToken,
        ]);

        if ($response->successful()) {
            $postId = $response->json('post_id') ?? $response->json('id', '');
            Log::info("Facebook image post published", ['post_id' => $postId]);

            return [
                'success' => true,
                'external_post_id' => $postId,
                'external_post_url' => "https://facebook.com/{$postId}",
            ];
        }

        $error = $response->json('error.message', $response->body());
        return ['success' => false, 'error' => "Facebook image error: {$error}"];
    }

    /**
     * Pubblica solo testo (endpoint feed).
     */
    private function publishTextOnly(string $pageId, string $accessToken, string $content): array
    {
        $response = Http::post(self::API_BASE . "/{$pageId}/feed", [
            'message' => $content,
            'access_token' => $accessToken,
        ]);

        if ($response->successful()) {
            $postId = $response->json('id', '');
            Log::info("Facebook text post published", ['post_id' => $postId]);

            return [
                'success' => true,
                'external_post_id' => $postId,
                'external_post_url' => "https://facebook.com/{$postId}",
            ];
        }

        $error = $response->json('error.message', $response->body());
        return ['success' => false, 'error' => "Facebook text error: {$error}"];
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
