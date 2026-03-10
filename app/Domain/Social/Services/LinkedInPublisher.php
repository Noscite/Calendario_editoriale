<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Post\Models\Post;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Publisher per LinkedIn.
 *
 * Replica esatta di publisher_service.py → publish_to_linkedin()
 * 3-step image upload: registerUpload → PUT binary → create ugcPost
 */
class LinkedInPublisher
{
    private const API_BASE = 'https://api.linkedin.com/v2';

    /**
     * Pubblica un post su LinkedIn.
     *
     * @return array{success: bool, external_post_id?: string, external_post_url?: string, error?: string}
     */
    public function publish(Post $post, SocialConnection $connection): array
    {
        try {
            // Costruisci contenuto con hashtags (spazio separatore, come Python)
            $content = $post->content ?? '';
            $hashtags = $post->hashtags ?? [];

            if (!empty($hashtags)) {
                $hashtagString = collect($hashtags)
                    ->map(fn (string $h) => str_starts_with($h, '#') ? $h : "#{$h}")
                    ->implode(' ');
                $content .= "\n\n{$hashtagString}";
            }

            // Determina author URN in base al tipo di account
            $externalId = $connection->external_account_id;
            $authorUrn = $connection->account_type === 'organization'
                ? "urn:li:organization:{$externalId}"
                : "urn:li:person:{$externalId}";

            $headers = [
                'Authorization' => "Bearer {$connection->access_token}",
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ];

            // Upload immagine se presente
            $mediaAsset = null;
            if ($post->image_url) {
                $mediaAsset = $this->uploadImage($post, $connection, $authorUrn, $headers);
            }

            // Costruisci payload del post
            $payload = $this->buildPostPayload($authorUrn, $content, $mediaAsset);

            // Pubblica ugcPost
            $response = Http::withHeaders($headers)
                ->post(self::API_BASE . '/ugcPosts', $payload);

            if ($response->successful()) {
                $postId = $response->json('id', '');
                Log::info("LinkedIn post published", ['post_id' => $post->id, 'linkedin_id' => $postId]);

                return [
                    'success' => true,
                    'external_post_id' => $postId,
                    'external_post_url' => "https://www.linkedin.com/feed/update/{$postId}",
                ];
            }

            $error = $response->json('message', $response->body());
            Log::error("LinkedIn publish failed", [
                'post_id' => $post->id,
                'status' => $response->status(),
                'error' => $error,
            ]);

            return ['success' => false, 'error' => "LinkedIn API error: {$error}"];
        } catch (\Throwable $e) {
            Log::error("LinkedIn publish exception", [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload immagine su LinkedIn (3-step).
     * 1. Register upload → ottieni uploadUrl + media asset
     * 2. PUT binary all'uploadUrl
     * 3. Restituisci media asset per il post
     */
    private function uploadImage(Post $post, SocialConnection $connection, string $authorUrn, array $headers): ?string
    {
        try {
            $imageUrl = $this->makeAbsoluteUrl($post->image_url);

            // Step 1: Register upload
            $registerPayload = [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => $authorUrn,
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent',
                        ],
                    ],
                ],
            ];

            $registerResponse = Http::withHeaders($headers)
                ->post(self::API_BASE . '/assets?action=registerUpload', $registerPayload);

            if (!$registerResponse->successful()) {
                Log::error("LinkedIn register upload failed", [
                    'status' => $registerResponse->status(),
                    'body' => $registerResponse->body(),
                ]);
                return null;
            }

            $registerData = $registerResponse->json();
            $uploadUrl = data_get($registerData, 'value.uploadMechanism.com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest.uploadUrl');
            $mediaAsset = data_get($registerData, 'value.asset');

            if (!$uploadUrl || !$mediaAsset) {
                Log::error("LinkedIn register upload: missing uploadUrl or asset");
                return null;
            }

            // Step 2: Upload binary
            $imageContent = $this->getImageContent($post->image_url);
            if (!$imageContent) {
                Log::error("LinkedIn upload: could not read image", ['url' => $post->image_url]);
                return null;
            }

            $uploadResponse = Http::withHeaders([
                'Authorization' => "Bearer {$connection->access_token}",
            ])->withBody($imageContent, 'application/octet-stream')
              ->put($uploadUrl);

            if (!$uploadResponse->successful()) {
                Log::error("LinkedIn image upload failed", [
                    'status' => $uploadResponse->status(),
                ]);
                return null;
            }

            Log::info("LinkedIn image uploaded", ['asset' => $mediaAsset]);
            return $mediaAsset;
        } catch (\Throwable $e) {
            Log::error("LinkedIn image upload exception", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Costruisci payload ugcPost.
     */
    private function buildPostPayload(string $authorUrn, string $content, ?string $mediaAsset): array
    {
        $shareContent = [
            'shareCommentary' => [
                'text' => $content,
            ],
        ];

        if ($mediaAsset) {
            $shareContent['shareMediaCategory'] = 'IMAGE';
            $shareContent['media'] = [
                [
                    'status' => 'READY',
                    'media' => $mediaAsset,
                ],
            ];
        } else {
            $shareContent['shareMediaCategory'] = 'NONE';
        }

        return [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => $shareContent,
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];
    }

    /**
     * Ottieni il contenuto binario dell'immagine.
     * Supporta path locali (storage) e URL remoti.
     */
    private function getImageContent(string $imageUrl): ?string
    {
        // Path locale (es. /uploads/posts/xxx.png)
        if (str_starts_with($imageUrl, '/')) {
            $localPath = storage_path('app/public' . $imageUrl);
            if (file_exists($localPath)) {
                return file_get_contents($localPath);
            }

            // Prova anche con il percorso storage diretto
            $storagePath = ltrim($imageUrl, '/');
            if (Storage::disk('public')->exists($storagePath)) {
                return Storage::disk('public')->get($storagePath);
            }
        }

        // URL remoto
        if (str_starts_with($imageUrl, 'http')) {
            try {
                $response = Http::timeout(30)->get($imageUrl);
                if ($response->successful()) {
                    return $response->body();
                }
            } catch (\Throwable $e) {
                Log::error("Failed to download image", ['url' => $imageUrl, 'error' => $e->getMessage()]);
            }
        }

        return null;
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
