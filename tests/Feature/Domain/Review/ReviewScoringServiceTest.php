<?php

declare(strict_types=1);

use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Services\ReviewScoringService;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.anthropic.api_key', 'sk-ant-fake-test');
    // L'observer dispatcha ScoreReviewJob alla creazione Review.
    // In sync mode farebbe partire una vera chiamata Anthropic prima di Http::fake().
    Queue::fake();
});

function attachAnthropicKey(int $brandId, string $value = 'sk-ant-brand-key'): void
{
    BrandApiKey::create([
        'brand_id'        => $brandId,
        'key_name'        => BrandApiKeyService::ANTHROPIC_API_KEY,
        'encrypted_value' => $value,
    ]);
}

/**
 * Build a Claude /v1/messages response wrapping an arbitrary scoring JSON.
 *
 * @param  array<string,mixed>  $scoring
 * @return array<string,mixed>
 */
function fakeClaudeScoringResponse(array $scoring): array
{
    return [
        'id'    => 'msg_test',
        'type'  => 'message',
        'role'  => 'assistant',
        'model' => 'claude-haiku-4-5-20251001',
        'content' => [[
            'type' => 'text',
            'text' => json_encode($scoring, JSON_UNESCAPED_UNICODE),
        ]],
        'stop_reason' => 'end_turn',
        'usage'       => ['input_tokens' => 100, 'output_tokens' => 50],
    ];
}

function makeReviewWithBrand(array $reviewOverrides = [], array $brandOverrides = []): Review
{
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org, $brandOverrides);
    attachAnthropicKey($brand->id);

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
        'reviewer_name'        => 'Mario Rossi',
        'rating'               => 5,
        'comment'              => 'Servizio fantastico, tornerò sicuramente!',
        'review_created_at'    => now()->subDay(),
        'fetched_at'           => now(),
        'status'               => 'new',
        'raw_payload'          => [],
    ], $reviewOverrides));
}

it('scores a positive review', function () {
    $review = makeReviewWithBrand([
        'rating'  => 5,
        'comment' => 'Esperienza fantastica, personale gentilissimo!',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeScoringResponse([
            'sentiment'             => 'positive',
            'urgency'               => 'low',
            'topics'                => ['service_quality'],
            'is_fake_suspect'       => false,
            'marketing_opportunity' => 'advocacy',
            'rationale'             => 'Cliente entusiasta, candidato ad ambassador.',
        ]), 200),
    ]);

    $result = app(ReviewScoringService::class)->score($review);

    expect($result['sentiment'])->toBe('positive');
    expect($result['urgency'])->toBe('low');
    expect($result['marketing_opportunity'])->toBe('advocacy');
    expect($result['topics'])->toBe(['service_quality']);
    expect($result['is_fake_suspect'])->toBeFalse();
    expect($result['rationale'])->toContain('ambassador');
});

it('scores legal-threat review as high urgency', function () {
    $review = makeReviewWithBrand([
        'rating'  => 1,
        'comment' => 'Vi denuncerò per truffa, contatterò il mio avvocato!',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeScoringResponse([
            'sentiment'             => 'negative',
            'urgency'               => 'high',
            'topics'                => ['altro'],
            'is_fake_suspect'       => false,
            'marketing_opportunity' => 'recovery',
            'rationale'             => 'Minaccia legale, intervento tempestivo.',
        ]), 200),
    ]);

    $result = app(ReviewScoringService::class)->score($review);

    expect($result['urgency'])->toBe('high');
    expect($result['sentiment'])->toBe('negative');
    expect($result['marketing_opportunity'])->toBe('recovery');
});

it('normalizes invalid enum values to safe defaults', function () {
    $review = makeReviewWithBrand();

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeScoringResponse([
            'sentiment'             => 'incredibly_amazing',
            'urgency'               => 'critical',
            'topics'                => null,
            'is_fake_suspect'       => 'maybe',
            'marketing_opportunity' => 'mega_upsell',
            'rationale'             => str_repeat('A', 500),
        ]), 200),
    ]);

    $result = app(ReviewScoringService::class)->score($review);

    expect($result['sentiment'])->toBe('neutral');
    expect($result['urgency'])->toBe('low');
    expect($result['topics'])->toBe(['altro']);
    expect($result['is_fake_suspect'])->toBeTrue(); // 'maybe' is truthy → bool cast
    expect($result['marketing_opportunity'])->toBe('none');
    expect(mb_strlen($result['rationale']))->toBeLessThanOrEqual(200);
});

it('uses brand ontology in the system prompt', function () {
    $ontology = [
        ['id' => 'food_quality', 'label' => 'Qualità del cibo', 'description' => '...'],
        ['id' => 'service_speed', 'label' => 'Velocità servizio', 'description' => '...'],
    ];
    $review = makeReviewWithBrand(brandOverrides: ['review_ontology' => $ontology]);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeScoringResponse([
            'sentiment'             => 'neutral',
            'urgency'               => 'low',
            'topics'                => ['food_quality'],
            'is_fake_suspect'       => false,
            'marketing_opportunity' => 'none',
            'rationale'             => 'ok',
        ]), 200),
    ]);

    app(ReviewScoringService::class)->score($review);

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['system'] ?? '';
        return is_string($system)
            && str_contains($system, 'food_quality')
            && str_contains($system, 'Velocità servizio');
    });
});

it('falls back to altro when no topic matches', function () {
    $review = makeReviewWithBrand();

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeScoringResponse([
            'sentiment'             => 'neutral',
            'urgency'               => 'low',
            'topics'                => [],
            'is_fake_suspect'       => false,
            'marketing_opportunity' => 'none',
            'rationale'             => 'nessun topic specifico',
        ]), 200),
    ]);

    $result = app(ReviewScoringService::class)->score($review);

    expect($result['topics'])->toBe(['altro']);
});
