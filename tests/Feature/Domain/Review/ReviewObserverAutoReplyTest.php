<?php

declare(strict_types=1);

use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Jobs\ProcessAutoReplyJob;
use App\Domain\Review\Jobs\ScoreReviewJob;
use App\Domain\Review\Models\Review;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function makeReviewForObserver(array $overrides = []): Review
{
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org);

    $conn = SocialConnection::create([
        'brand_id'              => $brand->id,
        'platform'              => 'google_business',
        'access_token'          => 'tok',
        'refresh_token'         => 'rtok',
        'token_expires_at'      => now()->addDay(),
        'external_account_id'   => '111',
        'external_account_name' => 'Loc',
        'account_type'          => '222',
        'is_active'             => true,
    ]);

    return Review::withoutGlobalScope('organization')->create(array_merge([
        'organization_id'      => $org->id,
        'social_connection_id' => $conn->id,
        'brand_id'             => $brand->id,
        'platform'             => 'google_business',
        'external_review_id'   => 'rev-' . uniqid(),
        'reviewer_name'        => 'Mario',
        'rating'               => 5,
        'comment'              => 'OK',
        'review_created_at'    => now()->subDay(),
        'fetched_at'           => now(),
        'status'               => ReviewStatus::New->value,
        'raw_payload'          => [],
    ], $overrides));
}

it('dispatches auto-reply on status change to scored', function () {
    $review = makeReviewForObserver();
    Queue::fake(); // reset to clear ScoreReviewJob from initial create()

    $review->update(['status' => ReviewStatus::Scored->value]);

    Queue::assertPushed(ProcessAutoReplyJob::class, function (ProcessAutoReplyJob $job) use ($review): bool {
        $r = new ReflectionClass($job);
        $p = $r->getProperty('reviewId');
        $p->setAccessible(true);
        return $p->getValue($job) === $review->id;
    });
});

it('does not dispatch if status was already scored', function () {
    $review = makeReviewForObserver(['status' => ReviewStatus::Scored->value]);
    Queue::fake();

    // Update other field, status stays scored
    $review->update(['comment' => 'edited']);

    Queue::assertNotPushed(ProcessAutoReplyJob::class);

    // Anche cambiando a un altro status diverso da new->scored, non triggera
    $review->update(['status' => ReviewStatus::Drafted->value]);
    Queue::assertNotPushed(ProcessAutoReplyJob::class);
});

it('does not dispatch for unrelated updates', function () {
    $review = makeReviewForObserver(['status' => ReviewStatus::New->value]);
    Queue::fake();

    $review->update(['comment' => 'edited but status invariato']);

    Queue::assertNotPushed(ProcessAutoReplyJob::class);
    Queue::assertNotPushed(ScoreReviewJob::class);
});
