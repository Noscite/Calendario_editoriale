<?php

declare(strict_types=1);

use App\Domain\Brand\Exceptions\MissingBrandApiKeyException;
use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Brand\Services\BrandApiKeyService;
use Illuminate\Support\Facades\Config;

const FALLBACK_CONFIG_PATH = 'services.anthropic.api_key';

it('returns brand key when configured', function () {
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org);
    BrandApiKey::create([
        'brand_id'        => $brand->id,
        'key_name'        => BrandApiKeyService::ANTHROPIC_API_KEY,
        'encrypted_value' => 'sk-ant-brand-own-key',
    ]);
    Config::set(FALLBACK_CONFIG_PATH, 'sk-ant-system-key');

    $value = app(BrandApiKeyService::class)->getWithSuperAdminFallback(
        $brand,
        BrandApiKeyService::ANTHROPIC_API_KEY,
        FALLBACK_CONFIG_PATH,
    );

    expect($value)->toBe('sk-ant-brand-own-key');
});

it('returns config fallback for superuser', function () {
    [$user, $org] = createAuthenticatedUser(['role' => 'superuser']);
    $brand        = createBrand($org);
    Config::set(FALLBACK_CONFIG_PATH, 'sk-ant-system-key');

    $this->actingAs($user);

    $value = app(BrandApiKeyService::class)->getWithSuperAdminFallback(
        $brand,
        BrandApiKeyService::ANTHROPIC_API_KEY,
        FALLBACK_CONFIG_PATH,
    );

    expect($value)->toBe('sk-ant-system-key');
});

it('returns config fallback for system tenant brand in cli', function () {
    [, $org] = createAuthenticatedUser();
    $org->update(['is_system_tenant' => true]);
    $brand   = createBrand($org);
    Config::set(FALLBACK_CONFIG_PATH, 'sk-ant-system-key');

    // Nessun actingAs: simula CLI / queue worker dove Auth::user() è null.

    $value = app(BrandApiKeyService::class)->getWithSuperAdminFallback(
        $brand,
        BrandApiKeyService::ANTHROPIC_API_KEY,
        FALLBACK_CONFIG_PATH,
    );

    expect($value)->toBe('sk-ant-system-key');
});

it('falls back to config for regular brand in cli/queue context', function () {
    // Aggiornato dopo fix queue-context: i job in coda (Horizon) non hanno
    // Auth user e non possono usare brand-level keys senza esplicita config
    // — il fallback al config è ora consentito anche per brand
    // non-system-tenant in CLI/queue. Vedi BrandApiKeyQueueContextTest per
    // copertura più dettagliata.
    [, $org] = createAuthenticatedUser();
    expect((bool) $org->is_system_tenant)->toBeFalse();
    $brand = createBrand($org);
    Config::set(FALLBACK_CONFIG_PATH, 'sk-ant-system-key');

    $value = app(BrandApiKeyService::class)->getWithSuperAdminFallback(
        $brand,
        BrandApiKeyService::ANTHROPIC_API_KEY,
        FALLBACK_CONFIG_PATH,
    );

    expect($value)->toBe('sk-ant-system-key');
});

it('throws when system config missing', function () {
    [, $org] = createAuthenticatedUser();
    $org->update(['is_system_tenant' => true]);
    $brand = createBrand($org);
    Config::set(FALLBACK_CONFIG_PATH, null);

    expect(fn () => app(BrandApiKeyService::class)->getWithSuperAdminFallback(
        $brand,
        BrandApiKeyService::ANTHROPIC_API_KEY,
        FALLBACK_CONFIG_PATH,
    ))->toThrow(RuntimeException::class, 'Chiave di sistema non configurata');
});
