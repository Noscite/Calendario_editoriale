<?php

declare(strict_types=1);

use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Review\Contracts\KnowledgeRetrieverInterface;
use App\Domain\Review\Enums\ReplyTone;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Services\ReviewReplyGenerator;
use App\Domain\Review\Services\StubKnowledgeRetriever;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.anthropic.api_key', 'sk-ant-fake-test');
    Queue::fake();
});

function fakeReplyResponse(string $body): array
{
    return [
        'id'    => 'msg_test',
        'type'  => 'message',
        'role'  => 'assistant',
        'model' => 'claude-sonnet-4-20250514',
        'content' => [[
            'type' => 'text',
            'text' => $body,
        ]],
        'stop_reason' => 'end_turn',
        'usage'       => ['input_tokens' => 200, 'output_tokens' => 80],
    ];
}

function makeReviewWithBrandForGenerator(array $reviewOverrides = [], array $brandOverrides = []): Review
{
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org, $brandOverrides);
    BrandApiKey::create([
        'brand_id'        => $brand->id,
        'key_name'        => BrandApiKeyService::ANTHROPIC_API_KEY,
        'encrypted_value' => 'sk-ant-brand-key',
    ]);

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
        'rating'               => 5,
        'comment'              => 'Esperienza ottima',
        'review_created_at'    => now()->subDay(),
        'fetched_at'           => now(),
        'status'               => 'scored',
        'sentiment'            => 'positive',
        'urgency'              => 'low',
        'topics'               => ['service_quality'],
        'marketing_opportunity'=> 'advocacy',
        'raw_payload'          => [],
    ], $reviewOverrides));
}

function makeGenerator(array $stubChunks = []): ReviewReplyGenerator
{
    app()->instance(KnowledgeRetrieverInterface::class, new StubKnowledgeRetriever($stubChunks));
    return app(ReviewReplyGenerator::class);
}

it('generates a reply using brand tone', function () {
    $review = makeReviewWithBrandForGenerator(brandOverrides: ['tone_of_voice' => 'amichevole e diretto']);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeReplyResponse('Grazie Mario, è bello sapere che ti sei trovato bene da noi!'), 200),
    ]);

    $result = makeGenerator()->generate($review);

    expect($result['body'])->toContain('Mario');
    expect($result['tone_used'])->toBe(ReplyTone::BrandDefault->value);
    expect($result['marketing_strategy'])->toBe('advocacy'); // dallo scoring
    expect($result['kb_chunks_used'])->toBe([]);
    expect($result['generated_by_model'])->toBe(ReviewReplyGenerator::MODEL);

    Http::assertSent(function (Request $req): bool {
        $sys = $req->data()['system'] ?? '';
        return is_string($sys) && str_contains($sys, 'amichevole e diretto');
    });
});

it('includes kb chunks in system prompt', function () {
    $review = makeReviewWithBrandForGenerator();
    $chunks = [
        ['chunk_id' => 1, 'content' => 'Politica resi: 30 giorni dall\'acquisto.', 'similarity' => 0.78, 'document_filename' => 'politiche.pdf'],
        ['chunk_id' => 2, 'content' => 'Spedizioni gratuite oltre 50€.', 'similarity' => 0.65, 'document_filename' => 'spedizioni.md'],
    ];

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeReplyResponse('Risposta che richiama politiche resi.'), 200),
    ]);

    $result = makeGenerator($chunks)->generate($review);

    expect($result['kb_chunks_used'])->toBe([1, 2]);

    Http::assertSent(function (Request $req): bool {
        $sys = $req->data()['system'] ?? '';
        return is_string($sys)
            && str_contains($sys, 'Politica resi')
            && str_contains($sys, 'politiche.pdf');
    });
});

it('applies deontological block for psicologia sector', function () {
    $review = makeReviewWithBrandForGenerator(brandOverrides: ['sector' => 'Psicologia clinica']);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeReplyResponse('Grazie del feedback, ti invito a un colloquio privato.'), 200),
    ]);

    makeGenerator()->generate($review);

    Http::assertSent(function (Request $req): bool {
        $sys = $req->data()['system'] ?? '';
        return is_string($sys)
            && str_contains($sys, 'VINCOLI DEONTOLOGICI')
            && str_contains($sys, 'segreto professionale');
    });
});

it('skips kb when retriever returns empty', function () {
    $review = makeReviewWithBrandForGenerator();

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeReplyResponse('Risposta breve senza KB.'), 200),
    ]);

    makeGenerator([])->generate($review);

    Http::assertSent(function (Request $req): bool {
        $sys = $req->data()['system'] ?? '';
        return is_string($sys) && str_contains($sys, 'nessun contesto KB rilevante disponibile');
    });
});

it('uses marketing strategy from scoring by default', function () {
    $review = makeReviewWithBrandForGenerator(['marketing_opportunity' => 'recovery']);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeReplyResponse('Recupero cliente.'), 200),
    ]);

    $result = makeGenerator()->generate($review);

    expect($result['marketing_strategy'])->toBe('recovery');

    Http::assertSent(function (Request $req): bool {
        $sys = $req->data()['system'] ?? '';
        return is_string($sys) && str_contains($sys, 'critica recuperabile');
    });
});

it('overrides strategy when explicit', function () {
    $review = makeReviewWithBrandForGenerator(['marketing_opportunity' => 'recovery']);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeReplyResponse('Forza testimonial!'), 200),
    ]);

    $result = makeGenerator()->generate($review, ReplyTone::BrandDefault, 'testimonial');

    expect($result['marketing_strategy'])->toBe('testimonial');

    Http::assertSent(function (Request $req): bool {
        $sys = $req->data()['system'] ?? '';
        return is_string($sys) && str_contains($sys, 'Recensione perfetta da promuovere');
    });
});

it('includes anti-hallucination block in system prompt', function () {
    $review = makeReviewWithBrandForGenerator();

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeReplyResponse('Risposta test'), 200),
    ]);

    makeGenerator()->generate($review);

    Http::assertSent(function (Request $req): bool {
        $sys = $req->data()['system'] ?? '';
        return is_string($sys)
            && str_contains($sys, 'VIETATO inventare')
            && str_contains($sys, 'SELF-CHECK')
            && str_contains($sys, 'letteralmente nei chunk');
    });
});

it('includes self-check instruction for facts', function () {
    $review = makeReviewWithBrandForGenerator();

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeReplyResponse('OK'), 200),
    ]);

    makeGenerator()->generate($review);

    Http::assertSent(function (Request $req): bool {
        $sys = $req->data()['system'] ?? '';
        return is_string($sys)
            && (str_contains($sys, 'verifica mentalmente')
                || str_contains($sys, 'Self-check')
                || str_contains($sys, 'SELF-CHECK'));
    });
});
