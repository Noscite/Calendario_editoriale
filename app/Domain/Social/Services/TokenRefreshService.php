<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Exceptions\TokenRefreshFailedException;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servizio per il refresh automatico dei token OAuth.
 *
 * Verifica se il token di una connessione social è in scadenza
 * (entro 1 ora) e in caso esegue il refresh tramite l'API del provider.
 *
 * Provider supportati:
 *   - facebook  (Meta Graph API)
 *   - instagram (Meta Graph API, stesso flusso di Facebook)
 *   - linkedin
 *   - google_business
 *
 * In caso di refresh fallito: aggiorna connection.status = 'expired'
 * e lancia TokenRefreshFailedException.
 */
final class TokenRefreshService
{
    /**
     * Esegue il refresh del token se in scadenza entro 1 ora.
     * Restituisce la connessione aggiornata (con il nuovo token).
     *
     * @throws TokenRefreshFailedException se il refresh fallisce
     */
    public function refreshIfNeeded(SocialConnection $connection): SocialConnection
    {
        // Se non c'è una scadenza impostata non facciamo nulla
        if ($connection->token_expires_at === null) {
            return $connection;
        }

        // Refresh solo se scade entro 1 ora
        if ($connection->token_expires_at->isAfter(now()->addHour())) {
            return $connection;
        }

        $platform = $connection->platform->value;

        Log::info("[TOKEN_REFRESH] Token in scadenza, avvio refresh", [
            'platform'         => $platform,
            'connection_id'    => $connection->id,
            'expires_at'       => $connection->token_expires_at->toIso8601String(),
        ]);

        return match ($platform) {
            'facebook', 'instagram' => $this->refreshMeta($connection),
            'linkedin'              => $this->refreshLinkedIn($connection),
            'google_business'       => $this->refreshGoogle($connection),
            default => $connection, // piattaforme senza refresh automatico
        };
    }

    // ──────────────────────────────────────────────────────────
    //  Provider-specific refresh
    // ──────────────────────────────────────────────────────────

    /**
     * Refresh token Meta (Facebook/Instagram).
     *
     * Meta non supporta il refresh tradizionale con refresh_token.
     * Estende la durata del long-lived token scambiando il token corrente.
     * Endpoint: GET /oauth/access_token?grant_type=fb_exchange_token
     *
     * @throws TokenRefreshFailedException
     */
    private function refreshMeta(SocialConnection $connection): SocialConnection
    {
        $response = Http::get('https://graph.facebook.com/v22.0/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => config('services.meta.app_id'),
            'client_secret'     => config('services.meta.app_secret'),
            'fb_exchange_token' => $connection->access_token,
        ]);

        if (!$response->successful()) {
            $error = $response->json('error.message', $response->body());
            Log::error("[TOKEN_REFRESH] Refresh Meta fallito", [
                'connection_id' => $connection->id,
                'error'         => $error,
            ]);
            return $this->markExpired($connection, $error);
        }

        $data        = $response->json();
        $newToken    = $data['access_token'];
        $expiresIn   = $data['expires_in'] ?? 5_184_000; // 60 giorni default Meta

        $connection->update([
            'access_token'     => $newToken,
            'token_expires_at' => now()->addSeconds($expiresIn),
        ]);

        Log::info("[TOKEN_REFRESH] Refresh Meta completato", [
            'connection_id' => $connection->id,
            'expires_in'    => $expiresIn,
        ]);

        return $connection->fresh();
    }

    /**
     * Refresh token LinkedIn tramite refresh_token.
     * Endpoint: POST https://www.linkedin.com/oauth/v2/accessToken
     *
     * @throws TokenRefreshFailedException
     */
    private function refreshLinkedIn(SocialConnection $connection): SocialConnection
    {
        if (!$connection->refresh_token) {
            Log::warning("[TOKEN_REFRESH] LinkedIn senza refresh_token, impossibile refreshare", [
                'connection_id' => $connection->id,
            ]);
            return $this->markExpired($connection, 'Nessun refresh_token disponibile');
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id'     => config('services.linkedin.client_id'),
            'client_secret' => config('services.linkedin.client_secret'),
        ]);

        if (!$response->successful()) {
            $error = $response->json('error_description', $response->body());
            Log::error("[TOKEN_REFRESH] Refresh LinkedIn fallito", [
                'connection_id' => $connection->id,
                'error'         => $error,
            ]);
            return $this->markExpired($connection, $error);
        }

        $data          = $response->json();
        $newToken      = $data['access_token'];
        $newRefresh    = $data['refresh_token'] ?? $connection->refresh_token;
        $expiresIn     = $data['expires_in'] ?? 5_184_000;

        $connection->update([
            'access_token'     => $newToken,
            'refresh_token'    => $newRefresh,
            'token_expires_at' => now()->addSeconds($expiresIn),
        ]);

        Log::info("[TOKEN_REFRESH] Refresh LinkedIn completato", [
            'connection_id' => $connection->id,
            'expires_in'    => $expiresIn,
        ]);

        return $connection->fresh();
    }

    /**
     * Refresh token Google Business tramite refresh_token.
     * Endpoint: POST https://oauth2.googleapis.com/token
     *
     * @throws TokenRefreshFailedException
     */
    private function refreshGoogle(SocialConnection $connection): SocialConnection
    {
        if (!$connection->refresh_token) {
            Log::warning("[TOKEN_REFRESH] Google senza refresh_token, impossibile refreshare", [
                'connection_id' => $connection->id,
            ]);
            return $this->markExpired($connection, 'Nessun refresh_token disponibile');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        if (!$response->successful()) {
            $error = $response->json('error_description', $response->body());
            Log::error("[TOKEN_REFRESH] Refresh Google fallito", [
                'connection_id' => $connection->id,
                'error'         => $error,
            ]);
            return $this->markExpired($connection, $error);
        }

        $data      = $response->json();
        $newToken  = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? 3600;

        $connection->update([
            'access_token'     => $newToken,
            'token_expires_at' => now()->addSeconds($expiresIn),
        ]);

        Log::info("[TOKEN_REFRESH] Refresh Google completato", [
            'connection_id' => $connection->id,
            'expires_in'    => $expiresIn,
        ]);

        return $connection->fresh();
    }

    // ──────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Marca la connessione come scaduta e lancia l'eccezione.
     *
     * @throws TokenRefreshFailedException sempre
     */
    private function markExpired(SocialConnection $connection, string $reason): never
    {
        $connection->update(['is_active' => false]);

        Log::error("[TOKEN_REFRESH] Connessione marcata come scaduta", [
            'connection_id' => $connection->id,
            'platform'      => $connection->platform->value,
            'reason'        => $reason,
        ]);

        throw new TokenRefreshFailedException($connection->platform->value, $reason);
    }
}
