<?php

declare(strict_types=1);

use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Organization\Models\Organization;
use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Jobs\SendReplyJob;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('services.anthropic.api_key', 'sk-ant-fake-test');
    Queue::fake();
});

function planWithReplies(int $monthlyReplyCount): Plan
{
    return Plan::firstOrCreate(['name' => 'reply-plan-' . $monthlyReplyCount], [
        'name'                         => 'reply-plan-' . $monthlyReplyCount,
        'display_name'                 => 'Plan ' . $monthlyReplyCount,
        'price_monthly'                => 50,
        'price_yearly'                 => 500,
        'max_brands'                   => 10,
        'max_users'                    => 10,
        'monthly_calendar_generations' => 100,
        'monthly_reply_count'          => $monthlyReplyCount,
        'monthly_text_tokens'          => 1000000,
        'monthly_images'               => 100,
        'is_active'                    => true,
        'allows_overage'               => false,
    ]);
}

function setupReviewWorld(int $monthlyReplyCount = 100): array
{
    $plan         = planWithReplies($monthlyReplyCount);
    [$user, $org] = createAuthenticatedUser([], ['plan_id' => $plan->id]);
    // Subscription attiva richiesta dal middleware
    \App\Domain\Subscription\Models\Subscription::create([
        'organization_id'        => $org->id,
        'plan_id'                => $plan->id,
        'status'                 => 'active',
        'paid_period_starts_at'  => now()->subMonth(),
        'paid_period_ends_at'    => now()->addMonth(),
    ]);

    $brand = createBrand($org);
    BrandApiKey::create([
        'brand_id'        => $brand->id,
        'key_name'        => BrandApiKeyService::ANTHROPIC_API_KEY,
        'encrypted_value' => 'sk-ant-brand',
    ]);

    $conn = SocialConnection::create([
        'brand_id'              => $brand->id,
        'platform'              => 'google_business',
        'access_token'          => 'token',
        'refresh_token'         => 'refresh',
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
        'rating'               => 4,
        'comment'              => 'Bella esperienza',
        'review_created_at'    => now()->subDay(),
        'fetched_at'           => now(),
        'status'               => ReviewStatus::Scored->value,
        'sentiment'            => 'positive',
        'urgency'              => 'low',
        'topics'               => ['service'],
        'marketing_opportunity'=> 'advocacy',
        'raw_payload'          => [],
    ]);

    return [$user, $org, $brand, $review];
}

function fakeAnthropicReply(string $text): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'id'   => 'msg', 'type' => 'message', 'role' => 'assistant',
            'model'=> 'claude-sonnet-4-20250514',
            'content' => [['type' => 'text', 'text' => $text]],
            'usage'   => ['input_tokens' => 100, 'output_tokens' => 50],
        ], 200),
    ]);
}

it('lists reviews for authenticated user', function () {
    [$user] = setupReviewWorld();
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/reviews');
    $res->assertOk()
        ->assertJsonPath('data.0.reviewer_name', 'Mario');

    expect($res->json('data'))->toHaveCount(1);
});

it('filters reviews by sentiment', function () {
    [$user, , , $review] = setupReviewWorld();
    Sanctum::actingAs($user);

    $this->getJson('/api/reviews?sentiment=positive')->assertOk()
        ->assertJsonPath('data.0.id', $review->id);

    $this->getJson('/api/reviews?sentiment=negative')->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns review detail with replies', function () {
    [$user, , , $review] = setupReviewWorld();
    Sanctum::actingAs($user);

    ReviewReply::create([
        'organization_id' => $review->organization_id,
        'review_id'       => $review->id,
        'status'          => ReplyStatus::Draft->value,
        'body'            => 'Una bozza',
    ]);

    $res = $this->getJson("/api/reviews/{$review->id}");
    $res->assertOk()
        ->assertJsonPath('id', $review->id)
        ->assertJsonPath('replies.0.body', 'Una bozza');
});

it('generates draft via POST endpoint', function () {
    [$user, , , $review] = setupReviewWorld();
    Sanctum::actingAs($user);
    fakeAnthropicReply('Grazie Mario, a presto!');

    $res = $this->postJson("/api/reviews/{$review->id}/draft", ['tone' => 'empathetic']);
    $res->assertCreated()
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('tone_used', 'empathetic');

    expect(ReviewReply::where('review_id', $review->id)->where('status', ReplyStatus::Draft->value)->count())->toBe(1);
});

it('updates draft body via PATCH', function () {
    [$user, , , $review] = setupReviewWorld();
    Sanctum::actingAs($user);

    $reply = ReviewReply::create([
        'organization_id' => $review->organization_id,
        'review_id'       => $review->id,
        'status'          => ReplyStatus::Draft->value,
        'body'            => 'originale',
    ]);

    $res = $this->patchJson("/api/reviews/{$review->id}/replies/{$reply->id}", ['body' => 'modificata']);
    $res->assertOk()
        ->assertJsonPath('body', 'modificata')
        ->assertJsonPath('was_edited', true);

    expect($reply->fresh()->original_body)->toBe('originale');
});

it('approves and dispatches send job', function () {
    [$user, , , $review] = setupReviewWorld(monthlyReplyCount: 50);
    Sanctum::actingAs($user);

    $reply = ReviewReply::create([
        'organization_id' => $review->organization_id,
        'review_id'       => $review->id,
        'status'          => ReplyStatus::Draft->value,
        'body'            => 'pronta',
    ]);

    $res = $this->postJson("/api/reviews/{$review->id}/replies/{$reply->id}/approve");
    $res->assertStatus(202)
        ->assertJsonPath('status', 'approved');

    Queue::assertPushed(SendReplyJob::class);
});

it('blocks approve when quota exceeded', function () {
    [$user, $org, , $review] = setupReviewWorld(monthlyReplyCount: 1);
    Sanctum::actingAs($user);

    // Pre-popola usage al limite
    \App\Domain\Subscription\Models\UsageLog::create([
        'organization_id' => $org->id,
        'period_start'    => now()->startOfMonth(),
        'period_end'      => now()->endOfMonth(),
        'replies_sent'    => 1,
    ]);

    $reply = ReviewReply::create([
        'organization_id' => $review->organization_id,
        'review_id'       => $review->id,
        'status'          => ReplyStatus::Draft->value,
        'body'            => 'pronta',
    ]);

    $res = $this->postJson("/api/reviews/{$review->id}/replies/{$reply->id}/approve");
    $res->assertStatus(403)
        ->assertJsonPath('error', 'quota_exceeded');

    Queue::assertNotPushed(SendReplyJob::class);
});

it('returns quota endpoint summary', function () {
    [$user] = setupReviewWorld(monthlyReplyCount: 50);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/reviews/quota');
    $res->assertOk()
        ->assertJsonStructure(['limit', 'used', 'remaining', 'unlimited', 'feature_enabled', 'resets_at'])
        ->assertJsonPath('limit', 50)
        ->assertJsonPath('feature_enabled', true)
        ->assertJsonPath('unlimited', false);
});
