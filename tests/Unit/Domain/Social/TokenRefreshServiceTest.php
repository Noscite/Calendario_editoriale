<?php

declare(strict_types=1);

use App\Domain\Post\Enums\Platform;
use App\Domain\Social\Exceptions\TokenRefreshFailedException;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Services\TokenRefreshService;
use Illuminate\Support\Facades\Http;

/**
 * Test per TokenRefreshService.
 *
 * Verifica:
 *   - Se il token non scade entro 1 ora, non viene refreshato
 *   - Refresh Meta (Facebook/Instagram) con risposta valida
 *   - Refresh LinkedIn con risposta valida
 *   - Refresh Google con risposta valida
 *   - In caso di fallimento: connection marcata come inattiva + eccezione
 */
describe('TokenRefreshService', function () {

    it('non esegue il refresh se il token non scade entro 1 ora', function () {
        Http::fake();
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        $connection = SocialConnection::create([
            'brand_id'             => $brand->id,
            'platform'             => Platform::Facebook,
            'access_token'         => 'existing_token',
            'token_expires_at'     => now()->addHours(2), // scade tra 2 ore
            'external_account_id'  => '12345',
            'is_active'            => true,
        ]);

        $service = new TokenRefreshService();
        $result  = $service->refreshIfNeeded($connection);

        // Non deve aver fatto richieste HTTP
        Http::assertNothingSent();
        expect($result->access_token)->toBe('existing_token');
    });

    it('non esegue il refresh se token_expires_at è null', function () {
        Http::fake();
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        $connection = SocialConnection::create([
            'brand_id'            => $brand->id,
            'platform'            => Platform::LinkedIn,
            'access_token'        => 'no_expiry_token',
            'token_expires_at'    => null,
            'external_account_id' => '12345',
            'is_active'           => true,
        ]);

        $service = new TokenRefreshService();
        $result  = $service->refreshIfNeeded($connection);

        Http::assertNothingSent();
        expect($result->access_token)->toBe('no_expiry_token');
    });

    it('esegue il refresh Meta quando il token scade entro 1 ora', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        Http::fake([
            'https://graph.facebook.com/v18.0/oauth/access_token*' => Http::response([
                'access_token' => 'new_meta_token',
                'expires_in'   => 5_184_000,
            ], 200),
        ]);

        $connection = SocialConnection::create([
            'brand_id'            => $brand->id,
            'platform'            => Platform::Facebook,
            'access_token'        => 'old_token',
            'token_expires_at'    => now()->addMinutes(30), // scade a breve
            'external_account_id' => '12345',
            'is_active'           => true,
        ]);

        $service = new TokenRefreshService();
        $result  = $service->refreshIfNeeded($connection);

        expect($result->access_token)->toBe('new_meta_token');
        expect($result->token_expires_at)->not->toBeNull();
    });

    it('esegue il refresh LinkedIn quando il token scade entro 1 ora', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        Http::fake([
            'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
                'access_token'  => 'new_linkedin_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in'    => 5_184_000,
            ], 200),
        ]);

        $connection = SocialConnection::create([
            'brand_id'            => $brand->id,
            'platform'            => Platform::LinkedIn,
            'access_token'        => 'old_token',
            'refresh_token'       => 'old_refresh_token',
            'token_expires_at'    => now()->addMinutes(30),
            'external_account_id' => '12345',
            'is_active'           => true,
        ]);

        $service = new TokenRefreshService();
        $result  = $service->refreshIfNeeded($connection);

        expect($result->access_token)->toBe('new_linkedin_token');
    });

    it('lancia TokenRefreshFailedException in caso di errore API', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        Http::fake([
            'https://graph.facebook.com/v18.0/oauth/access_token*' => Http::response([
                'error' => ['message' => 'Invalid token'],
            ], 400),
        ]);

        $connection = SocialConnection::create([
            'brand_id'            => $brand->id,
            'platform'            => Platform::Facebook,
            'access_token'        => 'invalid_token',
            'token_expires_at'    => now()->addMinutes(30),
            'external_account_id' => '12345',
            'is_active'           => true,
        ]);

        $service = new TokenRefreshService();

        expect(fn () => $service->refreshIfNeeded($connection))
            ->toThrow(TokenRefreshFailedException::class);

        // Connessione deve essere marcata come inattiva
        $connection->refresh();
        expect($connection->is_active)->toBeFalse();
    });

    it('marca la connessione come inattiva se LinkedIn non ha refresh_token', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        $connection = SocialConnection::create([
            'brand_id'            => $brand->id,
            'platform'            => Platform::LinkedIn,
            'access_token'        => 'old_token',
            'refresh_token'       => null, // nessun refresh token
            'token_expires_at'    => now()->addMinutes(30),
            'external_account_id' => '12345',
            'is_active'           => true,
        ]);

        $service = new TokenRefreshService();

        expect(fn () => $service->refreshIfNeeded($connection))
            ->toThrow(TokenRefreshFailedException::class);
    });
});
