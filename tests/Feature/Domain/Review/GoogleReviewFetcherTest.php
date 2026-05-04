<?php

declare(strict_types=1);

use App\Domain\Review\Models\Review;
use App\Domain\Review\Services\GoogleReviewFetcher;
use App\Domain\Social\Exceptions\TokenExpiredException;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // L'observer su Review dispatcha ScoreReviewJob alla creazione.
    // Qui il focus è il fetcher Google; lo scoring asincrono viene testato altrove.
    Queue::fake();
});

/**
 * Helper: crea una SocialConnection google_business agganciata a brand+org.
 */
function gbpConnection(): SocialConnection
{
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    return SocialConnection::create([
        'brand_id'              => $brand->id,
        'platform'              => 'google_business',
        'access_token'          => 'fake-access-token',
        'refresh_token'         => 'fake-refresh-token',
        'token_expires_at'      => now()->addDay(),
        'external_account_id'   => '111222333444',
        'external_account_name' => 'Test Location',
        'account_type'          => '999888777', // locationId
        'is_active'             => true,
        'connected_by_user_id'  => null,
    ]);
}

/** Payload realistico Google. */
function gbpReviewPayload(string $reviewId, string $star = 'FIVE', ?string $comment = 'Ottimo!'): array
{
    return [
        'reviewId'   => $reviewId,
        'reviewer'   => [
            'displayName'     => 'Mario Rossi',
            'profilePhotoUrl' => 'https://example.com/photo.jpg',
        ],
        'starRating' => $star,
        'comment'    => $comment,
        'createTime' => '2026-04-15T10:30:00Z',
        'updateTime' => '2026-04-15T10:30:00Z',
        'name'       => "accounts/111/locations/222/reviews/{$reviewId}",
    ];
}

it('fetches and stores new reviews', function () {
    $conn = gbpConnection();

    Http::fake([
        'mybusiness.googleapis.com/*' => Http::response([
            'reviews' => [
                gbpReviewPayload('rev-aaa', 'FIVE', 'Eccellente'),
                gbpReviewPayload('rev-bbb', 'FOUR', 'Buono'),
                gbpReviewPayload('rev-ccc', 'ONE', null),
            ],
        ], 200),
    ]);

    $stats = app(GoogleReviewFetcher::class)->fetchAndStore($conn);

    expect($stats['fetched'])->toBe(3);
    expect($stats['new'])->toBe(3);
    expect($stats['updated'])->toBe(0);

    $reviews = Review::withoutGlobalScope('organization')
        ->where('social_connection_id', $conn->id)
        ->orderBy('rating', 'desc')
        ->get();

    expect($reviews)->toHaveCount(3);
    expect($reviews[0]->rating)->toBe(5);
    expect($reviews[0]->external_review_id)->toBe('rev-aaa');
    expect($reviews[0]->reviewer_name)->toBe('Mario Rossi');
    expect($reviews[1]->rating)->toBe(4);
    expect($reviews[2]->rating)->toBe(1);
    expect($reviews[2]->comment)->toBeNull();
    expect($reviews[0]->organization_id)->toBe($conn->brand->organization_id);
    expect($reviews[0]->brand_id)->toBe($conn->brand_id);

    // Timer fetch reviews aggiornato dopo successo
    expect($conn->fresh()->last_reviews_fetched_at)->not->toBeNull();
});

it('dedupes existing reviews via updateOrCreate', function () {
    $conn = gbpConnection();

    // Pre-seed una review che esiste già
    Review::withoutGlobalScope('organization')->create([
        'organization_id'      => $conn->brand->organization_id,
        'social_connection_id' => $conn->id,
        'brand_id'             => $conn->brand_id,
        'platform'             => 'google_business',
        'external_review_id'   => 'rev-existing',
        'reviewer_name'        => 'Old Name',
        'rating'               => 3,
        'comment'              => 'Vecchio commento',
        'review_created_at'    => '2026-01-01 00:00:00',
        'fetched_at'           => '2026-01-01 00:00:00',
        'status'               => 'new',
        'raw_payload'          => ['old' => true],
    ]);

    Http::fake([
        'mybusiness.googleapis.com/*' => Http::response([
            'reviews' => [
                gbpReviewPayload('rev-existing', 'FIVE', 'Aggiornato!'), // edit
                gbpReviewPayload('rev-new', 'FOUR', 'Nuova review'),
            ],
        ], 200),
    ]);

    $stats = app(GoogleReviewFetcher::class)->fetchAndStore($conn);

    expect($stats['fetched'])->toBe(2);
    expect($stats['new'])->toBe(1);
    expect($stats['updated'])->toBe(1);

    $total = Review::withoutGlobalScope('organization')
        ->where('social_connection_id', $conn->id)
        ->count();
    expect($total)->toBe(2);

    $updated = Review::withoutGlobalScope('organization')
        ->where('external_review_id', 'rev-existing')
        ->first();
    expect($updated->rating)->toBe(5);
    expect($updated->comment)->toBe('Aggiornato!');
    expect($updated->raw_payload)->toMatchArray(['reviewId' => 'rev-existing']);
});

it('throws TokenExpiredException on 401', function () {
    $conn = gbpConnection();
    expect($conn->last_reviews_fetched_at)->toBeNull();

    Http::fake([
        'mybusiness.googleapis.com/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    try {
        app(GoogleReviewFetcher::class)->fetchAndStore($conn);
        $this->fail('TokenExpiredException not thrown');
    } catch (TokenExpiredException $e) {
        // Expected — verifica che il timer NON sia stato aggiornato in caso di 401
        expect($conn->fresh()->last_reviews_fetched_at)->toBeNull();
    }
});

it('paginates with nextPageToken', function () {
    $conn = gbpConnection();

    Http::fakeSequence('mybusiness.googleapis.com/*')
        ->push([
            'reviews' => [
                gbpReviewPayload('rev-page1-a', 'FIVE'),
                gbpReviewPayload('rev-page1-b', 'FOUR'),
            ],
            'nextPageToken' => 'TOKEN_PAGE_2',
        ], 200)
        ->push([
            'reviews' => [
                gbpReviewPayload('rev-page2-a', 'THREE'),
            ],
        ], 200);

    $stats = app(GoogleReviewFetcher::class)->fetchAndStore($conn);

    expect($stats['fetched'])->toBe(3);
    expect($stats['new'])->toBe(3);

    $count = Review::withoutGlobalScope('organization')
        ->where('social_connection_id', $conn->id)
        ->count();
    expect($count)->toBe(3);

    Http::assertSentCount(2);
});
