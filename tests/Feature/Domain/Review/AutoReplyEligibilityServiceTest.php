<?php

declare(strict_types=1);

use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Services\AutoReplyEligibilityService;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\UsageLog;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function planAllowingReplies(int $monthly = 100): Plan
{
    return Plan::firstOrCreate(['name' => 'auto-reply-plan-' . $monthly], [
        'name'                         => 'auto-reply-plan-' . $monthly,
        'display_name'                 => 'Plan ' . $monthly,
        'price_monthly'                => 50,
        'price_yearly'                 => 500,
        'max_brands'                   => 10,
        'max_users'                    => 10,
        'monthly_calendar_generations' => 100,
        'monthly_reply_count'          => $monthly,
        'monthly_text_tokens'          => 1000000,
        'monthly_images'               => 100,
        'is_active'                    => true,
        'allows_overage'               => false,
    ]);
}

function makeEligibilityWorld(array $brandOverrides = [], array $reviewOverrides = [], int $monthlyReplies = 100): array
{
    $plan         = planAllowingReplies($monthlyReplies);
    [, $org]      = createAuthenticatedUser([], ['plan_id' => $plan->id]);
    $brand        = createBrand($org, array_merge([
        'auto_reply_enabled'                 => true,
        'auto_reply_min_rating'              => 4,
        'auto_reply_only_positive_sentiment' => true,
        'auto_reply_tone'                    => 'brand_default',
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
        'comment'              => 'Top!',
        'review_created_at'    => now()->subDay(),
        'fetched_at'           => now(),
        'status'               => ReviewStatus::Scored->value,
        'sentiment'            => 'positive',
        'urgency'              => 'low',
        'topics'               => ['service'],
        'marketing_opportunity'=> 'advocacy',
        'raw_payload'          => [],
    ], $reviewOverrides));

    return [$review, $brand, $org];
}

it('blocks when brand auto reply disabled', function () {
    [$review] = makeEligibilityWorld(['auto_reply_enabled' => false]);
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeFalse();
    expect($check['reason'])->toBe('auto_reply_disabled');
});

it('blocks when rating below threshold', function () {
    [$review] = makeEligibilityWorld([], ['rating' => 3]);
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeFalse();
    expect($check['reason'])->toBe('rating_below_threshold');
});

it('blocks when sentiment not positive if configured', function () {
    [$review] = makeEligibilityWorld([], ['sentiment' => 'neutral']);
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeFalse();
    expect($check['reason'])->toBe('sentiment_not_positive');
});

it('allows when sentiment neutral if configured otherwise', function () {
    [$review] = makeEligibilityWorld(
        ['auto_reply_only_positive_sentiment' => false],
        ['sentiment' => 'neutral'],
    );
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeTrue();
});

it('blocks when fake suspect', function () {
    [$review] = makeEligibilityWorld([], ['is_fake_suspect' => true]);
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeFalse();
    expect($check['reason'])->toBe('fake_suspect');
});

it('blocks when urgency high', function () {
    [$review] = makeEligibilityWorld([], ['urgency' => 'high']);
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeFalse();
    expect($check['reason'])->toBe('urgency_high');
});

it('blocks when quota exceeded', function () {
    [$review, , $org] = makeEligibilityWorld([], [], 1);
    UsageLog::create([
        'organization_id' => $org->id,
        'period_start'    => now()->startOfMonth(),
        'period_end'      => now()->endOfMonth(),
        'replies_sent'    => 1,
    ]);
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeFalse();
    expect($check['reason'])->toBe('quota_exceeded');
});

it('blocks when review not scored yet', function () {
    [$review] = makeEligibilityWorld([], ['status' => ReviewStatus::New->value]);
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeFalse();
    expect($check['reason'])->toBe('not_scored');
});

it('returns eligible when all conditions met', function () {
    [$review] = makeEligibilityWorld();
    $check = app(AutoReplyEligibilityService::class)->check($review->fresh('brand'));
    expect($check['eligible'])->toBeTrue();
    expect($check['reason'])->toBeNull();
});
