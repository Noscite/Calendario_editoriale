<?php

declare(strict_types=1);

use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Brand\Services\BrandApiKeyService;
use Illuminate\Support\Facades\Config;

it('reports keys as missing when neither brand-level nor config fallback exist', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    Config::set('services.anthropic.api_key', null);
    Config::set('services.perplexity.api_key', null);

    $missing = app(BrandApiKeyService::class)->getMissingRequiredKeys($brand);

    expect($missing)->toHaveKey(BrandApiKeyService::ANTHROPIC_API_KEY);
    expect($missing)->toHaveKey(BrandApiKeyService::PERPLEXITY_API_KEY);
});

it('reports no missing keys when config fallback covers them', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    Config::set('services.anthropic.api_key', 'sk-ant-system-key');
    Config::set('services.perplexity.api_key', 'pplx-system-key');

    $missing = app(BrandApiKeyService::class)->getMissingRequiredKeys($brand);

    expect($missing)->toBe([]);
});

it('reports only the keys that are missing both brand-level and config', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    BrandApiKey::create([
        'brand_id'        => $brand->id,
        'key_name'        => BrandApiKeyService::ANTHROPIC_API_KEY,
        'encrypted_value' => 'sk-ant-brand-own-key',
    ]);

    Config::set('services.anthropic.api_key', null);
    Config::set('services.perplexity.api_key', null);

    $missing = app(BrandApiKeyService::class)->getMissingRequiredKeys($brand);

    expect($missing)->not->toHaveKey(BrandApiKeyService::ANTHROPIC_API_KEY);
    expect($missing)->toHaveKey(BrandApiKeyService::PERPLEXITY_API_KEY);
});

it('prefers brand-level key over config fallback in missing check', function () {
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    BrandApiKey::create([
        'brand_id'        => $brand->id,
        'key_name'        => BrandApiKeyService::ANTHROPIC_API_KEY,
        'encrypted_value' => 'sk-ant-brand-own-key',
    ]);
    BrandApiKey::create([
        'brand_id'        => $brand->id,
        'key_name'        => BrandApiKeyService::PERPLEXITY_API_KEY,
        'encrypted_value' => 'pplx-brand-own-key',
    ]);

    // Anche con config null, le chiavi brand-level sono presenti → niente missing
    Config::set('services.anthropic.api_key', null);
    Config::set('services.perplexity.api_key', null);

    $missing = app(BrandApiKeyService::class)->getMissingRequiredKeys($brand);

    expect($missing)->toBe([]);
});
