<?php

declare(strict_types=1);

use App\Domain\Brand\Exceptions\MissingBrandApiKeyException;
use App\Domain\Brand\Services\BrandApiKeyService;
use Illuminate\Support\Facades\Auth;

it('falls back to config in queue context for non-system-tenant brand', function () {
    config()->set('services.anthropic.api_key', 'sk-fake-system-key');

    [$user, $org] = createAuthenticatedUser([], ['is_system_tenant' => false]);
    $brand = createBrand($org);

    // Simula queue context: niente Auth user
    Auth::logout();

    $service = app(BrandApiKeyService::class);
    $key = $service->getWithSuperAdminFallback(
        $brand,
        BrandApiKeyService::ANTHROPIC_API_KEY,
        'services.anthropic.api_key'
    );

    expect($key)->toBe('sk-fake-system-key');
});

it('throws MissingBrandApiKeyException for non-system-tenant brand in HTTP context with non-superuser', function () {
    config()->set('services.anthropic.api_key', 'sk-fake-system-key');

    [$user, $org] = createAuthenticatedUser(
        ['role' => 'admin'],
        ['is_system_tenant' => false],
    );
    $brand = createBrand($org);

    // Simula HTTP context con utente loggato non-superuser.
    // runningInConsole() in test è true, ma Auth::user() != null,
    // quindi isQueueContext() è false e il check di superuser fallisce.
    Auth::login($user);

    $service = app(BrandApiKeyService::class);

    expect(fn () => $service->getWithSuperAdminFallback(
        $brand,
        BrandApiKeyService::ANTHROPIC_API_KEY,
        'services.anthropic.api_key'
    ))->toThrow(MissingBrandApiKeyException::class);
});

it('still allows fallback for system-tenant brand regardless of context', function () {
    config()->set('services.anthropic.api_key', 'sk-fake-system-key');

    [$user, $org] = createAuthenticatedUser([], ['is_system_tenant' => true]);
    $brand = createBrand($org);

    $service = app(BrandApiKeyService::class);
    $key = $service->getWithSuperAdminFallback(
        $brand,
        BrandApiKeyService::ANTHROPIC_API_KEY,
        'services.anthropic.api_key'
    );

    expect($key)->toBe('sk-fake-system-key');
});
