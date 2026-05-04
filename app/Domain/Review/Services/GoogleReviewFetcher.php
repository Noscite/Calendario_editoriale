<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use App\Domain\Social\Exceptions\TokenExpiredException;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetcher delle review da Google Business Profile API v4.
 *
 * Endpoint: GET https://mybusiness.googleapis.com/v4/{accountPath}/reviews
 *
 * Pattern di accountPath identico a GoogleBusinessPublisher:
 *   - Se external_account_id contiene già "/locations/" → usalo as-is
 *   - Altrimenti combina con account_type (locationId)
 *
 * Dedupe via UNIQUE(platform, social_connection_id, external_review_id).
 * Hard cap 200 review per call (sicurezza, location media << ).
 */
class GoogleReviewFetcher
{
    private const API_BASE      = 'https://mybusiness.googleapis.com/v4';
    private const PAGE_SIZE     = 50;
    private const HARD_CAP      = 200;

    /** @var array<string,int> Mapping Google starRating string → int */
    private const STAR_RATING_MAP = [
        'ONE'   => 1,
        'TWO'   => 2,
        'THREE' => 3,
        'FOUR'  => 4,
        'FIVE'  => 5,
    ];

    /**
     * Fetcha tutte le review della location e le persiste (updateOrCreate per dedupe).
     *
     * @return array{fetched: int, new: int, updated: int, errors: array<int,string>}
     *
     * @throws TokenExpiredException su HTTP 401
     */
    public function fetchAndStore(SocialConnection $connection): array
    {
        $accountPath = $this->resolveAccountPath($connection);
        $endpointBase = self::API_BASE . "/{$accountPath}/reviews";

        $stats = ['fetched' => 0, 'new' => 0, 'updated' => 0, 'errors' => []];
        $pageToken = null;
        $totalFetched = 0;

        do {
            $query = ['pageSize' => self::PAGE_SIZE];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken($connection->access_token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->get($endpointBase, $query);

            if ($response->status() === 401) {
                Log::warning('[REVIEW_FETCH] Token scaduto', ['connection_id' => $connection->id]);
                throw new TokenExpiredException('google_business');
            }

            if ($response->failed()) {
                $err = $response->json('error.message', $response->body());
                Log::error('[REVIEW_FETCH] HTTP ' . $response->status(), [
                    'connection_id' => $connection->id,
                    'error'         => $err,
                ]);
                $stats['errors'][] = "HTTP {$response->status()}: {$err}";
                break;
            }

            $data = $response->json();
            $reviews = $data['reviews'] ?? [];

            foreach ($reviews as $r) {
                $totalFetched++;
                if ($totalFetched > self::HARD_CAP) {
                    Log::info('[REVIEW_FETCH] Hard cap raggiunto', [
                        'connection_id' => $connection->id,
                        'cap'           => self::HARD_CAP,
                    ]);
                    break 2;
                }

                try {
                    $persisted = $this->upsertReview($connection, $r);
                    $stats['fetched']++;
                    if ($persisted['was_recently_created']) {
                        $stats['new']++;
                    } else {
                        $stats['updated']++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('[REVIEW_FETCH] Skip review', [
                        'connection_id'      => $connection->id,
                        'external_review_id' => $r['reviewId'] ?? 'unknown',
                        'error'              => $e->getMessage(),
                    ]);
                    $stats['errors'][] = $e->getMessage();
                }
            }

            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        // Aggiorna last_used_at — segnala che la connection è viva
        $connection->forceFill(['last_used_at' => now()])->save();

        Log::info('[REVIEW_FETCH] Fetch completato', [
            'connection_id' => $connection->id,
            'brand_id'      => $connection->brand_id,
            'fetched'       => $stats['fetched'],
            'new'           => $stats['new'],
            'updated'       => $stats['updated'],
            'errors'        => count($stats['errors']),
        ]);

        return $stats;
    }

    /** Risolve l'accountPath relativo (senza /v4/ in testa). */
    private function resolveAccountPath(SocialConnection $connection): string
    {
        $accountId  = (string) $connection->external_account_id;
        $locationId = (string) ($connection->account_type ?? '');

        // external_account_id può avere il formato "accounts/{id}/locations/{loc}" già completo
        if (str_contains($accountId, '/locations/')) {
            return $accountId;
        }

        // oppure può essere solo accountId con location separata
        return "accounts/{$accountId}/locations/{$locationId}";
    }

    /**
     * Upsert di una singola review. Ritorna info per stats (created vs updated).
     *
     * @return array{review: Review, was_recently_created: bool}
     */
    private function upsertReview(SocialConnection $connection, array $r): array
    {
        $externalId = $r['reviewId'] ?? null;
        if (! $externalId) {
            throw new \InvalidArgumentException('Missing reviewId in payload');
        }

        $starRatingStr = $r['starRating'] ?? '';
        $rating = self::STAR_RATING_MAP[$starRatingStr] ?? null;
        if ($rating === null) {
            throw new \InvalidArgumentException("Invalid starRating: {$starRatingStr}");
        }

        $reviewerName = $r['reviewer']['displayName'] ?? null;
        $reviewerPhoto = $r['reviewer']['profilePhotoUrl'] ?? null;
        $comment = $r['comment'] ?? null;
        $language = $r['comment'] ?? null
            ? null  // GBP non espone language come campo top-level; lasciamo null
            : null;

        $review = Review::withoutGlobalScope('organization')->updateOrCreate(
            [
                'platform'             => 'google_business',
                'social_connection_id' => $connection->id,
                'external_review_id'   => $externalId,
            ],
            [
                'organization_id'      => $connection->brand?->organization_id,
                'brand_id'             => $connection->brand_id,
                'reviewer_name'        => $reviewerName,
                'reviewer_photo_url'   => $reviewerPhoto,
                'rating'               => $rating,
                'comment'              => $comment,
                'language'             => $language,
                'review_created_at'    => $r['createTime'] ?? now(),
                'review_updated_at'    => $r['updateTime'] ?? null,
                'fetched_at'           => now(),
                // Non sovrascriviamo lo status se la review esisteva già (e magari è già 'replied')
                'raw_payload'          => $r,
            ]
        );

        // Se è una nuova review, assicuriamo status='new' (default DB)
        if ($review->wasRecentlyCreated && $review->status === null) {
            $review->forceFill(['status' => ReviewStatus::New->value])->save();
        }

        return [
            'review'               => $review,
            'was_recently_created' => $review->wasRecentlyCreated,
        ];
    }
}
