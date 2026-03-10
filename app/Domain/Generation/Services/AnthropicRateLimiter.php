<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate limiter per le chiamate all'API Anthropic.
 *
 * Usa il RateLimiter di Laravel (Redis) con chiave per organizzazione
 * per evitare di superare i limiti API.
 *
 * Limite configurabile via config('services.anthropic.requests_per_minute').
 * Default: 50 request/minuto.
 *
 * Integra con AnthropicApiClient — viene iniettato nel costruttore
 * e chiamato prima di ogni request API.
 */
final class AnthropicRateLimiter
{
    private int $requestsPerMinute;

    public function __construct(
        private readonly AnthropicApiClient $apiClient,
    ) {
        $this->requestsPerMinute = (int) config('services.anthropic.requests_per_minute', 50);
    }

    /**
     * Esegue una chiamata API Claude rispettando il rate limit per organizzazione.
     *
     * Se il limite è raggiunto, attende il tempo necessario (availableIn() + 1s)
     * prima di procedere.
     *
     * @param int    $organizationId ID organizzazione per isolamento rate limit
     * @param string $prompt         Prompt da inviare a Claude
     * @param int    $maxTokens      Numero massimo token di risposta
     * @param string $model          Modello Claude da usare
     *
     * @return array Risposta decodificata dell'API
     */
    public function call(
        int    $organizationId,
        string $prompt,
        int    $maxTokens,
        string $model = 'claude-sonnet-4-20250514',
    ): array {
        $key = "anthropic:{$organizationId}";

        // Aspetta se abbiamo superato il limite
        if (RateLimiter::tooManyAttempts($key, $this->requestsPerMinute)) {
            $waitSeconds = RateLimiter::availableIn($key) + 1;
            Log::info("[ANTHROPIC] Rate limit org #{$organizationId} — attesa {$waitSeconds}s");
            sleep($waitSeconds);
        }

        // Registra il tentativo (finestra 60 secondi)
        RateLimiter::hit($key, 60);

        return $this->apiClient->call($prompt, $maxTokens, $model);
    }

    /**
     * Verifica se l'organizzazione è al limite senza effettuare la chiamata.
     * Utile per polling e preview.
     */
    public function isThrottled(int $organizationId): bool
    {
        return RateLimiter::tooManyAttempts("anthropic:{$organizationId}", $this->requestsPerMinute);
    }

    /**
     * Restituisce i secondi prima che il rate limit si resetti per l'organizzazione.
     */
    public function availableIn(int $organizationId): int
    {
        return RateLimiter::availableIn("anthropic:{$organizationId}");
    }

    /**
     * Restituisce il numero di tentativi rimanenti per l'organizzazione.
     */
    public function remaining(int $organizationId): int
    {
        return max(
            0,
            $this->requestsPerMinute - RateLimiter::attempts("anthropic:{$organizationId}")
        );
    }
}
