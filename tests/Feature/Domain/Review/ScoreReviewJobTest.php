<?php

declare(strict_types=1);

use App\Domain\Review\Enums\MarketingOpportunity;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Enums\Sentiment;
use App\Domain\Review\Enums\Urgency;
use App\Domain\Review\Jobs\ScoreReviewJob;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Services\ReviewScoringService;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

function makeNewReviewForJob(array $overrides = []): Review
{
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org);

    $conn = SocialConnection::create([
        'brand_id'              => $brand->id,
        'platform'              => 'google_business',
        'access_token'          => 'fake',
        'refresh_token'         => 'fake',
        'token_expires_at'      => now()->addDay(),
        'external_account_id'   => 'acc',
        'external_account_name' => 'Acc',
        'account_type'          => 'loc',
        'is_active'             => true,
    ]);

    return Review::withoutGlobalScope('organization')->create(array_merge([
        'organization_id'      => $org->id,
        'social_connection_id' => $conn->id,
        'brand_id'             => $brand->id,
        'platform'             => 'google_business',
        'external_review_id'   => 'rev-' . uniqid(),
        'reviewer_name'        => 'Mario',
        'rating'               => 4,
        'comment'              => 'Buona esperienza',
        'review_created_at'    => now(),
        'fetched_at'           => now(),
        'status'               => ReviewStatus::New->value,
        'raw_payload'          => [],
    ], $overrides));
}

it('scores review and updates status', function () {
    Queue::fake(); // Evita dispatch dell'observer durante create()
    $review = makeNewReviewForJob();
    Queue::swap(app('queue'));

    $this->mock(ReviewScoringService::class, function (MockInterface $m) {
        $m->shouldReceive('score')
            ->once()
            ->andReturn([
                'sentiment'             => 'positive',
                'urgency'               => 'low',
                'topics'                => ['service_quality'],
                'is_fake_suspect'       => false,
                'marketing_opportunity' => 'advocacy',
                'rationale'             => 'cliente entusiasta',
            ]);
    });

    (new ScoreReviewJob($review->id))->handle(app(ReviewScoringService::class));

    $fresh = Review::withoutGlobalScope('organization')->find($review->id);
    expect($fresh->sentiment)->toBe(Sentiment::Positive);
    expect($fresh->urgency)->toBe(Urgency::Low);
    expect($fresh->marketing_opportunity)->toBe(MarketingOpportunity::Advocacy);
    expect($fresh->topics)->toBe(['service_quality']);
    expect($fresh->is_fake_suspect)->toBeFalse();
    expect($fresh->scoring_rationale)->toBe('cliente entusiasta');
    expect($fresh->status)->toBe(ReviewStatus::Scored);
    expect($fresh->scored_at)->not->toBeNull();
    expect($fresh->scored_by_model)->toBe(ReviewScoringService::MODEL);
});

it('skips already scored review (idempotenza)', function () {
    Queue::fake();
    $review = makeNewReviewForJob();
    $review->update([
        'sentiment'             => Sentiment::Positive->value,
        'urgency'               => Urgency::Low->value,
        'topics'                => ['service_quality'],
        'marketing_opportunity' => MarketingOpportunity::Advocacy->value,
        'scoring_rationale'     => 'già fatto',
        'scored_by_model'       => 'claude-haiku-4-5-20251001',
        'scored_at'             => now()->subHour(),
        'status'                => ReviewStatus::Scored->value,
    ]);
    Queue::swap(app('queue'));

    $this->mock(ReviewScoringService::class, function (MockInterface $m) {
        $m->shouldNotReceive('score');
    });

    (new ScoreReviewJob($review->id))->handle(app(ReviewScoringService::class));

    expect($review->fresh()->scoring_rationale)->toBe('già fatto');
});

it('dispatches via observer on review creation', function () {
    Queue::fake();

    $review = makeNewReviewForJob();

    Queue::assertPushed(ScoreReviewJob::class, function (ScoreReviewJob $job) use ($review): bool {
        $reflection = new ReflectionClass($job);
        $prop       = $reflection->getProperty('reviewId');
        $prop->setAccessible(true);
        return $prop->getValue($job) === $review->id;
    });
});
