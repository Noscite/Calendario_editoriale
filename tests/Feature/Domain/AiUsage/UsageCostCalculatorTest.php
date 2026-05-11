<?php

declare(strict_types=1);

use App\Domain\AiUsage\Services\UsageCostCalculator;

beforeEach(function () {
    $this->calc = app(UsageCostCalculator::class);
});

it('calculates Anthropic Sonnet cost from response', function () {
    $response = ['usage' => ['input_tokens' => 3000, 'output_tokens' => 500]];

    $record = $this->calc->fromAnthropic($response, 'claude-sonnet-4-6', 'test_purpose');

    // Sonnet 4.6: $3/M in + $15/M out
    // 3000 * 3/1M = 0.009  +  500 * 15/1M = 0.0075  = 0.0165
    expect($record->costUsd)->toBeGreaterThan(0.016);
    expect($record->costUsd)->toBeLessThan(0.017);
    expect($record->purpose)->toBe('test_purpose');
    expect($record->model)->toBe('claude-sonnet-4-6');
    expect($record->inputTokens)->toBe(3000);
    expect($record->outputTokens)->toBe(500);
});

it('handles cache tokens (creation + read)', function () {
    $response = [
        'usage' => [
            'input_tokens' => 500,
            'output_tokens' => 200,
            'cache_creation_input_tokens' => 2000,
            'cache_read_input_tokens' => 1000,
        ],
    ];

    $record = $this->calc->fromAnthropic($response, 'claude-sonnet-4-6');

    // 500*3/1M + 200*15/1M + 2000*3.75/1M + 1000*0.3/1M
    // = 0.0015 + 0.003 + 0.0075 + 0.0003 = 0.0123
    expect($record->cacheCreationTokens)->toBe(2000);
    expect($record->cacheReadTokens)->toBe(1000);
    expect($record->costUsd)->toBeGreaterThan(0.012);
    expect($record->costUsd)->toBeLessThan(0.013);
});

it('calculates image gen cost', function () {
    $record = $this->calc->fromImageGen(
        provider: 'openai_images',
        model:    'gpt-image-1',
        size:     '1024x1024_standard',
        count:    1,
    );

    expect($record->costUsd)->toBe(0.04);
    expect($record->imageCount)->toBe(1);
    expect($record->imageSize)->toBe('1024x1024_standard');
});

it('converts USD to EUR', function () {
    $response = ['usage' => ['input_tokens' => 1_000_000, 'output_tokens' => 0]];

    $record = $this->calc->fromAnthropic($response, 'claude-sonnet-4-6');

    // 1M input = $3 → ~€2.79 (con cambio default 0.93)
    expect($record->costUsd)->toBe(3.00);
    expect($record->costEur)->toBeGreaterThan(2.78);
    expect($record->costEur)->toBeLessThan(2.80);
});

it('falls back to default pricing for unknown model', function () {
    $response = ['usage' => ['input_tokens' => 1000, 'output_tokens' => 500]];

    $record = $this->calc->fromAnthropic($response, 'claude-vaporware-99');

    // Default = Sonnet pricing: $3/$15
    // 1000*3/1M + 500*15/1M = 0.003 + 0.0075 = 0.0105
    expect($record->costUsd)->toBeGreaterThan(0.0104);
    expect($record->costUsd)->toBeLessThan(0.0106);
});

it('UsageRecord serializes to array with all fields', function () {
    $response = ['usage' => ['input_tokens' => 100, 'output_tokens' => 50]];
    $record = $this->calc->fromAnthropic($response, 'claude-sonnet-4-6', 'serialization_test');

    $arr = $record->toArray();
    expect($arr)->toHaveKeys([
        'provider', 'model', 'input_tokens', 'output_tokens',
        'cache_creation_tokens', 'cache_read_tokens',
        'image_count', 'image_size',
        'cost_usd', 'cost_eur', 'purpose',
    ]);
    expect($arr['provider'])->toBe('anthropic');
    expect($arr['purpose'])->toBe('serialization_test');
});
