<?php

declare(strict_types=1);

use App\Domain\Brand\Exceptions\MissingBrandApiKeyException;
use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Brand\Services\BrandApiKeyService;

it('returns brand key when configured', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);
    BrandApiKey::create([
        'brand_id'        => $brand->id,
        'key_name'        => BrandApiKeyService::META_APP_ID,
        'encrypted_value' => 'fb-app-123',
    ]);

    $value = app(BrandApiKeyService::class)->get($brand, BrandApiKeyService::META_APP_ID);

    expect($value)->toBe('fb-app-123');
});

it('returns null when key is not configured', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    $value = app(BrandApiKeyService::class)->get($brand, BrandApiKeyService::META_APP_ID);

    expect($value)->toBeNull();
});

it('getRequired throws when key is missing', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    expect(fn () => app(BrandApiKeyService::class)
        ->getRequired($brand, BrandApiKeyService::META_APP_ID)
    )->toThrow(MissingBrandApiKeyException::class);
});

it('getMissingRequiredKeys always returns empty array (AI keys are env-served)', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org); // nessuna chiave configurata

    $missing = app(BrandApiKeyService::class)->getMissingRequiredKeys($brand);

    expect($missing)->toBe([]);
});

it('set silently ignores AI keys (anthropic/openai/perplexity)', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    app(BrandApiKeyService::class)->set($brand, BrandApiKeyService::ANTHROPIC_API_KEY, 'sk-ant-xxx');
    app(BrandApiKeyService::class)->set($brand, BrandApiKeyService::OPENAI_API_KEY, 'sk-xxx');
    app(BrandApiKeyService::class)->set($brand, BrandApiKeyService::PERPLEXITY_API_KEY, 'pplx-xxx');

    expect(BrandApiKey::where('brand_id', $brand->id)->count())->toBe(0);
});

it('set persists social keys normally', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    app(BrandApiKeyService::class)->set($brand, BrandApiKeyService::META_APP_ID, 'fb-123');

    expect(app(BrandApiKeyService::class)->get($brand, BrandApiKeyService::META_APP_ID))
        ->toBe('fb-123');
});

it('saveMany filters AI keys but persists social keys', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    app(BrandApiKeyService::class)->saveMany($brand, [
        BrandApiKeyService::ANTHROPIC_API_KEY  => 'sk-ant-xxx', // ignorata
        BrandApiKeyService::META_APP_ID        => 'fb-app-456', // persistita
        BrandApiKeyService::LINKEDIN_CLIENT_ID => 'li-789',     // persistita
    ]);

    expect(BrandApiKey::where('brand_id', $brand->id)->pluck('key_name')->toArray())
        ->toContain('meta_app_id', 'linkedin_client_id')
        ->not->toContain('anthropic_api_key');
});

it('groups() does not expose AI section anymore', function () {
    $groups = BrandApiKeyService::groups();

    expect(array_keys($groups))
        ->toContain('Meta (Facebook / Instagram)', 'LinkedIn', 'Google Business Profile')
        ->not->toContain('AI');
});
