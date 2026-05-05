<?php

declare(strict_types=1);

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();
});

function planForCancelTest(): Plan
{
    return Plan::firstOrCreate(['name' => 'cancel-test-plan'], [
        'name'                         => 'cancel-test-plan',
        'display_name'                 => 'Cancel Test',
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

function makeReplyForCancel(string $status = 'draft', bool $autoApproved = true): array
{
    $plan         = planForCancelTest();
    [$user, $org] = createAuthenticatedUser([], ['plan_id' => $plan->id]);
    Subscription::create([
        'organization_id'        => $org->id,
        'plan_id'                => $plan->id,
        'status'                 => 'active',
        'paid_period_starts_at'  => now()->subMonth(),
        'paid_period_ends_at'    => now()->addMonth(),
    ]);

    $brand = createBrand($org);
    $conn  = SocialConnection::create([
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

    $review = Review::withoutGlobalScope('organization')->create([
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
        'status'               => ReviewStatus::Drafted->value,
        'raw_payload'          => [],
    ]);

    $reply = ReviewReply::create([
        'organization_id'   => $org->id,
        'review_id'         => $review->id,
        'status'            => $status,
        'body'              => 'auto-bozza',
        'was_auto_approved' => $autoApproved,
    ]);

    return [$user, $review, $reply];
}

it('cancels auto-approved draft reply', function () {
    [$user, $review, $reply] = makeReplyForCancel(status: 'draft', autoApproved: true);
    Sanctum::actingAs($user);

    $res = $this->postJson("/api/reviews/{$review->id}/replies/{$reply->id}/cancel");
    $res->assertStatus(204);

    expect($reply->fresh()->status)->toBe(ReplyStatus::Superseded);
});

it('rejects cancel for manual draft', function () {
    [$user, $review, $reply] = makeReplyForCancel(status: 'draft', autoApproved: false);
    Sanctum::actingAs($user);

    $res = $this->postJson("/api/reviews/{$review->id}/replies/{$reply->id}/cancel");
    $res->assertStatus(422)
        ->assertJsonPath('error', 'invalid_reply');

    expect($reply->fresh()->status)->toBe(ReplyStatus::Draft);
});

it('rejects cancel for already sent reply', function () {
    [$user, $review, $reply] = makeReplyForCancel(status: 'sent', autoApproved: true);
    Sanctum::actingAs($user);

    $res = $this->postJson("/api/reviews/{$review->id}/replies/{$reply->id}/cancel");
    $res->assertStatus(422)
        ->assertJsonPath('error', 'invalid_reply');

    expect($reply->fresh()->status)->toBe(ReplyStatus::Sent);
});
