<?php

declare(strict_types=1);

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Jobs\ProcessAutoReplyJob;
use App\Domain\Review\Jobs\SendReplyJob;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Review\Notifications\AutoReplyPreInvioNotification;
use App\Domain\Review\Services\ReviewReplyGenerator;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Subscription\Models\Plan;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
});

function planForAutoReply(): Plan
{
    return Plan::firstOrCreate(['name' => 'auto-reply-test-plan'], [
        'name'                         => 'auto-reply-test-plan',
        'display_name'                 => 'Auto Reply Test',
        'price_monthly'                => 50,
        'price_yearly'                 => 500,
        'max_brands'                   => 10,
        'max_users'                    => 10,
        'monthly_calendar_generations' => 100,
        'monthly_reply_count'          => 100,
        'monthly_text_tokens'          => 1000000,
        'monthly_images'               => 100,
        'is_active'                    => true,
        'allows_overage'               => false,
    ]);
}

function makeAutoReplyWorld(array $brandOverrides = [], array $reviewOverrides = []): array
{
    $plan         = planForAutoReply();
    [$user, $org] = createAuthenticatedUser([], ['plan_id' => $plan->id]);

    // Crea un secondo user con role admin per ricevere le notifiche
    User::create([
        'email'           => 'admin-' . uniqid() . '@test.com',
        'password'        => 'pwd',
        'full_name'       => 'Admin Notify',
        'organization_id' => $org->id,
        'role'            => 'admin',
        'is_active'       => true,
    ]);

    $brand = createBrand($org, array_merge([
        'auto_reply_enabled'                 => true,
        'auto_reply_min_rating'              => 4,
        'auto_reply_only_positive_sentiment' => true,
        'auto_reply_tone'                    => 'gratitude',
        'auto_reply_review_mode'             => false,
        'auto_reply_delay_minutes'           => 30,
    ], $brandOverrides));

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

    $review = Review::withoutGlobalScope('organization')->create(array_merge([
        'organization_id'      => $org->id,
        'social_connection_id' => $conn->id,
        'brand_id'             => $brand->id,
        'platform'             => 'google_business',
        'external_review_id'   => 'rev-' . uniqid(),
        'reviewer_name'        => 'Mario',
        'rating'               => 5,
        'comment'              => 'Bravissimi',
        'review_created_at'    => now()->subDay(),
        'fetched_at'           => now(),
        'status'               => ReviewStatus::Scored->value,
        'sentiment'            => 'positive',
        'urgency'              => 'low',
        'topics'               => ['service'],
        'marketing_opportunity'=> 'advocacy',
        'raw_payload'          => [],
    ], $reviewOverrides));

    return [$review, $brand, $org, $user];
}

function mockGenerator(string $body = 'Grazie Mario, a presto!'): void
{
    test()->mock(ReviewReplyGenerator::class, function (MockInterface $m) use ($body) {
        $m->shouldReceive('generate')
            ->andReturn([
                'body'                => $body,
                'tone_used'           => 'gratitude',
                'marketing_strategy'  => 'advocacy',
                'kb_chunks_used'      => [],
                'generated_by_model'  => 'claude-sonnet-4-20250514',
                'input_tokens'        => 100,
                'output_tokens'       => 50,
            ]);
    });
}

it('skips when not eligible', function () {
    [$review] = makeAutoReplyWorld(['auto_reply_enabled' => false]);

    test()->mock(ReviewReplyGenerator::class, function (MockInterface $m) {
        $m->shouldNotReceive('generate');
    });

    (new ProcessAutoReplyJob($review->id))->handle(
        app(\App\Domain\Review\Services\AutoReplyEligibilityService::class),
        app(ReviewReplyGenerator::class),
    );

    expect(ReviewReply::where('review_id', $review->id)->count())->toBe(0);
    Queue::assertNotPushed(SendReplyJob::class);
});

it('creates reply with was_auto_approved flag', function () {
    [$review] = makeAutoReplyWorld();
    mockGenerator();

    (new ProcessAutoReplyJob($review->id))->handle(
        app(\App\Domain\Review\Services\AutoReplyEligibilityService::class),
        app(ReviewReplyGenerator::class),
    );

    $reply = ReviewReply::where('review_id', $review->id)->first();
    expect($reply)->not->toBeNull();
    expect($reply->was_auto_approved)->toBeTrue();
    expect($reply->approved_by_user_id)->toBeNull();
    expect($reply->status)->toBe(ReplyStatus::Approved); // immediate mode default
});

it('dispatches send immediately when review_mode disabled', function () {
    [$review] = makeAutoReplyWorld(['auto_reply_review_mode' => false]);
    mockGenerator();

    (new ProcessAutoReplyJob($review->id))->handle(
        app(\App\Domain\Review\Services\AutoReplyEligibilityService::class),
        app(ReviewReplyGenerator::class),
    );

    Queue::assertPushed(SendReplyJob::class, function (SendReplyJob $job): bool {
        return $job->delay === null || $job->delay === 0;
    });
});

it('delays send when review_mode enabled', function () {
    [$review] = makeAutoReplyWorld(['auto_reply_review_mode' => true, 'auto_reply_delay_minutes' => 45]);
    mockGenerator();

    (new ProcessAutoReplyJob($review->id))->handle(
        app(\App\Domain\Review\Services\AutoReplyEligibilityService::class),
        app(ReviewReplyGenerator::class),
    );

    $reply = ReviewReply::where('review_id', $review->id)->first();
    expect($reply->status)->toBe(ReplyStatus::Draft);
    expect($reply->approved_at)->toBeNull();
    expect($reply->notify_after_send)->toBeFalse();

    Queue::assertPushed(SendReplyJob::class, function (SendReplyJob $job): bool {
        return $job->delay !== null;
    });
});

it('sends pre-invio email in review mode', function () {
    [$review, , , $user] = makeAutoReplyWorld(['auto_reply_review_mode' => true]);
    mockGenerator();

    (new ProcessAutoReplyJob($review->id))->handle(
        app(\App\Domain\Review\Services\AutoReplyEligibilityService::class),
        app(ReviewReplyGenerator::class),
    );

    Notification::assertSentTimes(AutoReplyPreInvioNotification::class, 1);
});

it('does not send pre-invio email in immediate mode', function () {
    [$review] = makeAutoReplyWorld(['auto_reply_review_mode' => false]);
    mockGenerator();

    (new ProcessAutoReplyJob($review->id))->handle(
        app(\App\Domain\Review\Services\AutoReplyEligibilityService::class),
        app(ReviewReplyGenerator::class),
    );

    Notification::assertSentTimes(AutoReplyPreInvioNotification::class, 0);

    $reply = ReviewReply::where('review_id', $review->id)->first();
    expect($reply->notify_after_send)->toBeTrue();
});
