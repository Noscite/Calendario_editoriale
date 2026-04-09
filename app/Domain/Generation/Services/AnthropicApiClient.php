<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Client HTTP per l'API Anthropic Messages.
 *
 * Estratto da ClaudeContentGenerator per isolare:
 *   - La chiamata HTTP all'API (/v1/messages)
 *   - Il retry logic (3 tentativi con backoff esponenziale)
 *   - La gestione errori 429 (rate limit) con RateLimiter
 *   - Il parsing della risposta JSON (parseJsonResponse)
 *
 * Limite configurabile via config('services.anthropic.requests_per_minute').
 * Default: 50 request/minuto.
 */
final class AnthropicApiClient
{
    private const API_URL   = 'https://api.anthropic.com/v1/messages';
    private const MAX_TRIES = 3;

    private string $apiKey;
    private int    $requestsPerMinute;

    public function __construct()
    {
        $this->apiKey            = config('services.anthropic.api_key', '');
        $this->requestsPerMinute = (int) config('services.anthropic.requests_per_minute', 50);
    }

    /**
     * Esegue una chiamata all'API Claude con retry logic e rate limiting.
     *
     * Retry: 3 tentativi con backoff esponenziale (2^attempt secondi).
     * Rate limit 429: attende i secondi suggeriti dall'header Retry-After.
     *
     * @return array Risposta decodificata (usage, content, etc.)
     *
     * @throws RuntimeException se tutti i tentativi falliscono
     */
    public function call(string $prompt, int $maxTokens, string $model = 'claude-sonnet-4-20250514'): array
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_TRIES; $attempt++) {
            if ($attempt > 0) {
                $backoffSeconds = (int) pow(2, $attempt);
                Log::info("[ANTHROPIC] Retry {$attempt}/" . (self::MAX_TRIES - 1) . " — attesa {$backoffSeconds}s");
                sleep($backoffSeconds);
            }

            try {
                $response = Http::withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                    ->timeout(120)
                    ->post(self::API_URL, [
                        'model'      => $model,
                        'max_tokens' => $maxTokens,
                        'system'     => 'Sei un esperto di content marketing e social media. Rispondi SEMPRE e SOLO con JSON valido, senza markdown, senza testo aggiuntivo prima o dopo il JSON.',
                        'messages'   => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                    ]);

                // Gestione rate limit 429
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                    Log::warning("[ANTHROPIC] Rate limit 429 — attesa {$retryAfter}s", [
                        'attempt' => $attempt,
                    ]);
                    sleep($retryAfter + 1);
                    continue;
                }

                if ($response->failed()) {
                    $body          = $response->body();
                    $lastException = new RuntimeException("Claude API error: HTTP {$response->status()} — {$body}");
                    Log::error("[ANTHROPIC] HTTP {$response->status()}", ['body' => $body, 'attempt' => $attempt]);
                    continue;
                }

                return $response->json();

            } catch (\Throwable $e) {
                $lastException = $e;
                Log::error("[ANTHROPIC] Eccezione tentativo {$attempt}", ['error' => $e->getMessage()]);
            }
        }

        throw $lastException ?? new RuntimeException('Claude API: tutti i tentativi falliti');
    }

    /**
     * Come call(), ma usa il prompt caching di Anthropic (beta).
     *
     * Il contenuto statico ($staticContent, ≥1024 token) viene marcato con
     * cache_control=ephemeral: la prima chiamata lo scrive in cache (costo
     * leggermente superiore), le successive lo leggono a ~10% del costo normale.
     * Ideale per batch 2-N della stessa generazione, dove brand/personas/guidelines
     * sono identici e solo le date cambiano.
     *
     * @param string $staticContent  Parte del prompt identica tra i batch (brand, personas, guidelines)
     * @param string $dynamicContent Parte variabile (periodo date del batch corrente)
     */
    public function callCached(string $staticContent, string $dynamicContent, int $maxTokens, string $model): array
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_TRIES; $attempt++) {
            if ($attempt > 0) {
                $backoffSeconds = (int) pow(2, $attempt);
                Log::info("[ANTHROPIC] Retry {$attempt}/" . (self::MAX_TRIES - 1) . " — attesa {$backoffSeconds}s");
                sleep($backoffSeconds);
            }

            try {
                $content = [
                    [
                        'type'          => 'text',
                        'text'          => $staticContent,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ];

                if ($dynamicContent !== '') {
                    $content[] = ['type' => 'text', 'text' => $dynamicContent];
                }

                $response = Http::withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta'    => 'prompt-caching-2024-07-31',
                    'content-type'      => 'application/json',
                ])
                    ->timeout(120)
                    ->post(self::API_URL, [
                        'model'      => $model,
                        'max_tokens' => $maxTokens,
                        'system'     => 'Sei un esperto di content marketing e social media. Rispondi SEMPRE e SOLO con JSON valido, senza markdown, senza testo aggiuntivo prima o dopo il JSON.',
                        'messages'   => [['role' => 'user', 'content' => $content]],
                    ]);

                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                    Log::warning("[ANTHROPIC] Rate limit 429 — attesa {$retryAfter}s", ['attempt' => $attempt]);
                    sleep($retryAfter + 1);
                    continue;
                }

                if ($response->failed()) {
                    $body          = $response->body();
                    $lastException = new RuntimeException("Claude API error: HTTP {$response->status()} — {$body}");
                    Log::error("[ANTHROPIC] HTTP {$response->status()}", ['body' => $body, 'attempt' => $attempt]);
                    continue;
                }

                $data  = $response->json();
                $usage = $data['usage'] ?? [];
                Log::info('[ANTHROPIC] Cache — created=' . ($usage['cache_creation_input_tokens'] ?? 0) . ' read=' . ($usage['cache_read_input_tokens'] ?? 0));

                return $data;

            } catch (\Throwable $e) {
                $lastException = $e;
                Log::error("[ANTHROPIC] Eccezione tentativo {$attempt}", ['error' => $e->getMessage()]);
            }
        }

        throw $lastException ?? new RuntimeException('Claude API: tutti i tentativi falliti');
    }

    /**
     * Parsa la risposta JSON di Claude, rimuovendo eventuali backtick markdown.
     * Replica esatta della logica Python:
     *
     *   if content.startswith("```"):
     *       content = content.split("```")[1]
     *       if content.startswith("json"):
     *           content = content[4:]
     *   content = content.strip()
     */
    public function parseJsonResponse(string $content): array
    {
        if (str_starts_with($content, '```')) {
            $parts   = explode('```', $content);
            $content = $parts[1] ?? $content;
            if (str_starts_with($content, 'json')) {
                $content = substr($content, 4);
            }
        }

        $content = trim($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Verifica se siamo vicini al rate limit e aspetta se necessario.
     * Usa il RateLimiter di Laravel con chiave per organizzazione.
     *
     * @param int $organizationId ID organizzazione per chiave rate limit
     */
    public function checkRateLimit(int $organizationId): void
    {
        $key = "anthropic:{$organizationId}";

        if (RateLimiter::tooManyAttempts($key, $this->requestsPerMinute)) {
            $waitSeconds = RateLimiter::availableIn($key) + 1;
            Log::info("[ANTHROPIC] Rate limit org #{$organizationId} — attesa {$waitSeconds}s");
            sleep($waitSeconds);
        }

        RateLimiter::hit($key, 60); // 60 secondi finestra
    }
}
