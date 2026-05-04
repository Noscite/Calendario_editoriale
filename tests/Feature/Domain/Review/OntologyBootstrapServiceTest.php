<?php

declare(strict_types=1);

use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Review\Services\OntologyBootstrapService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.anthropic.api_key', 'sk-ant-fake-test');
});

function attachAnthropicKeyForOntologyTest(int $brandId): void
{
    BrandApiKey::create([
        'brand_id'        => $brandId,
        'key_name'        => BrandApiKeyService::ANTHROPIC_API_KEY,
        'encrypted_value' => 'sk-ant-brand-key',
    ]);
}

/**
 * @param  array<int, array<string,string>>  $ontology
 */
function fakeClaudeOntologyResponse(array $ontology): array
{
    return [
        'id'    => 'msg_test',
        'type'  => 'message',
        'role'  => 'assistant',
        'model' => OntologyBootstrapService::MODEL,
        'content' => [[
            'type' => 'text',
            'text' => json_encode($ontology, JSON_UNESCAPED_UNICODE),
        ]],
        'stop_reason' => 'end_turn',
        'usage'       => ['input_tokens' => 200, 'output_tokens' => 300],
    ];
}

it('generates ontology from brand metadata', function () {
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org, ['sector' => 'Ristorazione', 'description' => 'Trattoria toscana']);
    attachAnthropicKeyForOntologyTest($brand->id);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeOntologyResponse([
            ['id' => 'food_quality',   'label' => 'Qualità del cibo',    'description' => 'Gusto, freschezza, presentazione.'],
            ['id' => 'service_speed',  'label' => 'Velocità del servizio', 'description' => 'Tempi di attesa.'],
            ['id' => 'ambience',       'label' => 'Atmosfera',            'description' => 'Locale e arredamento.'],
        ]), 200),
    ]);

    $ontology = app(OntologyBootstrapService::class)->bootstrapForBrand($brand);

    expect($ontology)->toBeArray();
    expect(count($ontology))->toBeGreaterThanOrEqual(3);

    // Verifica che il body della richiesta contenga il contesto del brand
    Http::assertSent(function (Request $request) use ($brand): bool {
        $messages = $request->data()['messages'] ?? [];
        $userText = $messages[0]['content'] ?? '';
        return is_string($userText)
            && str_contains($userText, $brand->name)
            && str_contains($userText, 'Ristorazione');
    });
});

it('always includes altro topic', function () {
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org);
    attachAnthropicKeyForOntologyTest($brand->id);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeOntologyResponse([
            ['id' => 'topic_a', 'label' => 'A', 'description' => 'a'],
            ['id' => 'topic_b', 'label' => 'B', 'description' => 'b'],
        ]), 200),
    ]);

    $ontology = app(OntologyBootstrapService::class)->bootstrapForBrand($brand);

    $ids = array_column($ontology, 'id');
    expect($ids)->toContain('altro');

    // Non duplica "altro" se già presente nella risposta del modello
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeOntologyResponse([
            ['id' => 'topic_a', 'label' => 'A', 'description' => 'a'],
            ['id' => 'altro',   'label' => 'Altro', 'description' => 'fallback'],
        ]), 200),
    ]);

    $brand2 = createBrand($org, ['name' => 'Brand B']);
    attachAnthropicKeyForOntologyTest($brand2->id);
    $ontology2 = app(OntologyBootstrapService::class)->bootstrapForBrand($brand2);
    expect(array_count_values(array_column($ontology2, 'id'))['altro'])->toBe(1);
});

it('persists ontology to brand', function () {
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org);
    attachAnthropicKeyForOntologyTest($brand->id);

    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeOntologyResponse([
            ['id' => 'delivery', 'label' => 'Consegna', 'description' => 'Tempi e qualità'],
        ]), 200),
    ]);

    expect($brand->fresh()->review_ontology)->toBeNull();

    app(OntologyBootstrapService::class)->bootstrapForBrand($brand);

    $reloaded = $brand->fresh()->review_ontology;
    expect($reloaded)->toBeArray();
    expect(array_column($reloaded, 'id'))->toContain('delivery');
    expect(array_column($reloaded, 'id'))->toContain('altro');
});
