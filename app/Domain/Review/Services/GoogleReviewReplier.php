<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Review\Models\Review;
use App\Domain\Social\Exceptions\TokenExpiredException;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Invio reply a Google Business Profile.
 *
 * Endpoint: PUT https://mybusiness.googleapis.com/v4/{accountPath}/reviews/{reviewId}/reply
 * Body: {"comment": "<text>"}
 *
 * Stessa logica di resolve account/location di GoogleBusinessPublisher:
 * external_account_id può essere già il path completo, o solo l'accountId
 * (con locationId in account_type).
 */
final class GoogleReviewReplier
{
    private const API_BASE = 'https://mybusiness.googleapis.com/v4';

    /**
     * @return array{success: bool, external_reply_id?: string, error?: string}
     *
     * @throws TokenExpiredException
     */
    public function reply(Review $review, SocialConnection $connection, string $body): array
    {
        try {
            $accountId  = $connection->external_account_id;
            $locationId = $connection->account_type ?? '';

            if (str_contains((string) $accountId, '/locations/')) {
                $base = self::API_BASE . "/{$accountId}";
            } else {
                $base = self::API_BASE . "/accounts/{$accountId}/locations/{$locationId}";
            }

            $endpoint = $base . "/reviews/{$review->external_review_id}/reply";

            Log::info('[GBP_REPLY] Invio reply', [
                'review_id'          => $review->id,
                'external_review_id' => $review->external_review_id,
                'endpoint'           => $endpoint,
            ]);

            $response = Http::withToken($connection->access_token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->put($endpoint, ['comment' => $body]);

            if ($response->status() === 401) {
                Log::warning('[GBP_REPLY] Token scaduto', ['review_id' => $review->id]);
                throw new TokenExpiredException('google_business');
            }

            if ($response->successful()) {
                $data        = $response->json();
                $replyName   = $data['name'] ?? null;
                $updateTime  = $data['updateTime'] ?? null;

                Log::info('[GBP_REPLY] Reply pubblicata', [
                    'review_id'  => $review->id,
                    'reply_name' => $replyName,
                    'updated'    => $updateTime,
                ]);

                return [
                    'success'           => true,
                    'external_reply_id' => $replyName !== null
                        ? (string) $replyName
                        : (string) $review->external_review_id . '/reply',
                ];
            }

            $error = $response->json('error.message', $response->body());

            Log::error('[GBP_REPLY] Invio fallito', [
                'review_id' => $review->id,
                'status'    => $response->status(),
                'error'     => $error,
            ]);

            return ['success' => false, 'error' => "GBP API error: {$error}"];

        } catch (TokenExpiredException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[GBP_REPLY] Eccezione invio', [
                'review_id' => $review->id,
                'error'     => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
