<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Jobs\SendReplyJob;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Review\Services\GoogleReviewReplier;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Services\TokenRefreshService;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Services\ReplyQuotaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function makeApprovedReply(int $monthlyReplyCount = 100): array
{
    [, $org] = createAuthenticatedUser();
    $plan    = Plan::firstOrCreate(['name' => 'pro_test'], [
        'name'                         => 'pro_test',
        'display_name'                 => 'Pro Test',
        'price_monthly'                => 99,
        'price_yearly'                 => 990,
        'max_brands'                   => 10,
        'max_users'                    => 10,
        'monthly_calendar_generations' => 100,
        'monthly_reply_count'          => $monthlyReplyCount,
        'monthly_text_tokens'          => 1000000,
        'monthly_images'               => 1000,
        'is_active'                    => true,
        'allows_overage'               => false,
    ]);
    $org->update(['plan_id' => $plan->id]);

    $brand = createBrand($org);
    $conn  = SocialConnection::create([
        'brand_id'              => $brand->id,
        'platform'              => 'google_business',
        'access_token'          => 'access-tok',
        'refresh_token'         => 'refresh-tok',
        'token_expires_at'      => now()->addDay(),
        'external_account_id'   => '111',
        'external_account_name' => 'L',
        'account_type'          => '222',
        'is_active'             => true,
    ]);

    $review = Review::withoutGlobalScope('organization')->create([
        'organization_id'      => $org->id,
        'social_connection_id' => $conn->id,
        'brand_id'             => $brand->id,
        'platform'             => 'google_business',
        'external_review_id'   => 'rev-123',
        'reviewer_name'        => 'Mario',
        'rating'               => 5,
        'comment'              => 'Top!',
        'review_created_at'    => now()->subDay(),
        'fetched_at'           => now(),
        'status'               => ReviewStatus::Drafted->value,
        'raw_payload'          => [],
    ]);

    $reply = ReviewReply::create([
        'organization_id' => $org->id,
        'review_id'       => $review->id,
        'status'          => ReplyStatus::Approved->value,
        'body'            => 'Grazie Mario, a presto!',
        'approved_at'     => now(),
    ]);

    return [$reply, $review, $org, $conn];
}

function runSendJob(int $replyId): void
{
    (new SendReplyJob($replyId))->handle(
        app(GoogleReviewReplier::class),
        app(TokenRefreshService::class),
        app(ReplyQuotaService::class),
    );
}

it('sends reply to Google Business API', function () {
    [$reply, $review] = makeApprovedReply();

    Http::fake([
        'mybusiness.googleapis.com/*' => Http::response([
            'name'       => "accounts/111/locations/222/reviews/rev-123/reply",
            'comment'    => 'Grazie Mario, a presto!',
            'updateTime' => '2026-05-05T10:00:00Z',
        ], 200),
    ]);

    runSendJob($reply->id);

    Http::assertSent(function (\Illuminate\Http\Client\Request $req) {
        return str_contains($req->url(), '/reviews/rev-123/reply')
            && $req->method() === 'PUT'
            && ($req->data()['comment'] ?? null) === 'Grazie Mario, a presto!';
    });
});

it('marks reply as sent and updates review status', function () {
    [$reply, $review] = makeApprovedReply();

    Http::fake([
        'mybusiness.googleapis.com/*' => Http::response(['name' => 'reply/abc', 'updateTime' => '2026-05-05T10:00:00Z'], 200),
    ]);

    runSendJob($reply->id);

    $reply  = $reply->fresh();
    $review = $review->fresh();

    expect($reply->status)->toBe(ReplyStatus::Sent);
    expect($reply->external_reply_id)->toBe('reply/abc');
    expect($reply->sent_at)->not->toBeNull();
    expect($review->status)->toBe(ReviewStatus::Replied);
});

it('decrements subscription quota on success', function () {
    [$reply, , $org] = makeApprovedReply(50);

    Http::fake([
        'mybusiness.googleapis.com/*' => Http::response(['name' => 'reply/x'], 200),
    ]);

    $quotaBefore = app(ReplyQuotaService::class)->repliesUsedThisMonth($org);
    runSendJob($reply->id);
    $quotaAfter = app(ReplyQuotaService::class)->repliesUsedThisMonth($org);

    expect($quotaAfter)->toBe($quotaBefore + 1);
});

it('refreshes token on 401 and retries', function () {
    [$reply, $review] = makeApprovedReply();

    Http::fakeSequence('mybusiness.googleapis.com/*')
        ->push(['error' => 'unauthorized'], 401)
        ->push(['name' => 'reply/after-refresh', 'updateTime' => '2026-05-05T10:00:00Z'], 200);

    runSendJob($reply->id);

    $reply = $reply->fresh();
    expect($reply->status)->toBe(ReplyStatus::Sent);
    expect($reply->external_reply_id)->toBe('reply/after-refresh');
    Http::assertSentCount(2);
});

it('marks failed on definitive error and rethrows', function () {
    [$reply] = makeApprovedReply();

    Http::fake([
        'mybusiness.googleapis.com/*' => Http::response(['error' => ['message' => 'Server error']], 500),
    ]);

    try {
        runSendJob($reply->id);
        $this->fail('Expected RuntimeException');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('Send reply failed');
    }

    $reply = $reply->fresh();
    expect($reply->status)->toBe(ReplyStatus::Failed);
    expect($reply->error_message)->not->toBeNull();
    expect($reply->retry_count)->toBe(1);
});
