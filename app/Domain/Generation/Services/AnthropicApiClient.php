<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\AiUsage\Data\UsageRecord;
use App\Domain\AiUsage\Services\UsageCostCalculator;
use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandApiKeyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Client HTTP per l'API Anthropic Messages.
 *
 * Il system prompt è iniettabile per chiamata. Se non specificato,
 * usa DEFAULT_SYSTEM_PROMPT (baseline minimale per caller generici).
 * I caller di content generation passano system prompt specializzati
 * via SystemPromptLibrary.
 *
 * In callCached() il system prompt è cachato indipendentemente dalla
 * parte statica del messaggio utente: 2 cache breakpoints separati.
 */
final class AnthropicApiClient
{
    private const API_URL   = 'https://api.anthropic.com/v1/messages';
    private const MAX_TRIES = 3;

    private const DEFAULT_SYSTEM_PROMPT = <<<'TXT'
Sei un assistente AI italiano. Rispondi SEMPRE e SOLO con JSON valido,
senza markdown, senza testo prima o dopo il JSON, senza spiegazioni.
TXT;

    private string $apiKey;
    private int    $requestsPerMinute;

    public function __construct()
    {
        $this->apiKey            = config('services.anthropic.api_key', '');
        $this->requestsPerMinute = (int) config('services.anthropic.requests_per_minute', 50);
    }

    public function withBrand(?Brand $brand): static
    {
        if ($brand) {
            $key = app(BrandApiKeyService::class)->getWithSuperAdminFallback(
                $brand,
                BrandApiKeyService::ANTHROPIC_API_KEY,
                'services.anthropic.api_key'
            );
            $clone         = clone $this;
            $clone->apiKey = $key;
            return $clone;
        }
        return $this;
    }

    /**
     * Variante "morbida" per i percorsi sincroni innescati da un utente autenticato
     * (generazione post AI, rigenerazione, campagne). Usa la chiave del brand se
     * configurata a mano, altrimenti MANTIENE la chiave di sistema corrente SENZA
     * lanciare — a differenza di withBrand(), che pretende superuser/system-tenant/
     * queue per il fallback. Evita di bloccare la generazione dei brand che non hanno
     * (ancora) una chiave propria, replicando il comportamento del job in coda.
     */
    public function withBrandOrSystemFallback(?Brand $brand): static
    {
        if (! $brand) {
            return $this;
        }

        $key = app(BrandApiKeyService::class)->get($brand, BrandApiKeyService::ANTHROPIC_API_KEY);

        if ($key === null || $key === '') {
            return $this; // nessuna chiave brand-level → resta sulla chiave di sistema
        }

        $clone         = clone $this;
        $clone->apiKey = $key;
        return $clone;
    }

    /**
     * @param  array<int, array{name: string, url: string, api_key: ?string}>  $mcpServers
     *         MCP connectors da iniettare nella request (opzionale). Quando non
     *         vuoto attiva l'header beta MCP. Vedi formatMcpServers().
     */
    public function call(
        string  $prompt,
        int     $maxTokens,
        string  $model = 'claude-opus-4-8',
        ?string $systemPrompt = null,
        array   $mcpServers = [],
    ): array {
        $system        = $systemPrompt ?? self::DEFAULT_SYSTEM_PROMPT;
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_TRIES; $attempt++) {
            if ($attempt > 0) {
                $backoffSeconds = (int) pow(2, $attempt);
                Log::info("[ANTHROPIC] Retry {$attempt}/" . (self::MAX_TRIES - 1) . " — attesa {$backoffSeconds}s");
                sleep($backoffSeconds);
            }

            try {
                $headers = [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ];

                $body = [
                    'model'      => $model,
                    'max_tokens' => $maxTokens,
                    'system'     => $system,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ];

                $formattedMcp = $this->formatMcpServers($mcpServers);
                if (! empty($formattedMcp)) {
                    $body['mcp_servers']        = $formattedMcp;
                    $headers['anthropic-beta']  = 'mcp-client-2025-04-04';
                }

                $response = Http::withHeaders($headers)
                    ->timeout(600)
                    ->post(self::API_URL, $body);

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

                return $response->json();

            } catch (\Throwable $e) {
                $lastException = $e;
                Log::error("[ANTHROPIC] Eccezione tentativo {$attempt}", ['error' => $e->getMessage()]);
            }
        }

        throw $lastException ?? new RuntimeException('Claude API: tutti i tentativi falliti');
    }

    /**
     * @param  array<int, array{name: string, url: string, api_key: ?string}>  $mcpServers
     */
    public function callCached(
        string  $staticContent,
        string  $dynamicContent,
        int     $maxTokens,
        string  $model,
        ?string $systemPrompt = null,
        ?string $secondStaticContent = null,
        array   $mcpServers = [],
    ): array {
        $system        = $systemPrompt ?? self::DEFAULT_SYSTEM_PROMPT;
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_TRIES; $attempt++) {
            if ($attempt > 0) {
                $backoffSeconds = (int) pow(2, $attempt);
                Log::info("[ANTHROPIC] Retry {$attempt}/" . (self::MAX_TRIES - 1) . " — attesa {$backoffSeconds}s");
                sleep($backoffSeconds);
            }

            try {
                $systemBlocks = [
                    ['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']],
                ];

                $content = [
                    ['type' => 'text', 'text' => $staticContent, 'cache_control' => ['type' => 'ephemeral']],
                ];
                if ($secondStaticContent !== null && $secondStaticContent !== '') {
                    $content[] = ['type' => 'text', 'text' => $secondStaticContent, 'cache_control' => ['type' => 'ephemeral']];
                }
                if ($dynamicContent !== '') {
                    $content[] = ['type' => 'text', 'text' => $dynamicContent];
                }

                // Beta headers: prompt-caching obbligatorio per cache_control,
                // mcp-client opzionale quando mcpServers non vuoto.
                $betaHeaders = ['prompt-caching-2024-07-31'];
                $body = [
                    'model'      => $model,
                    'max_tokens' => $maxTokens,
                    'system'     => $systemBlocks,
                    'messages'   => [['role' => 'user', 'content' => $content]],
                ];

                $formattedMcp = $this->formatMcpServers($mcpServers);
                if (! empty($formattedMcp)) {
                    $body['mcp_servers']  = $formattedMcp;
                    $betaHeaders[]        = 'mcp-client-2025-04-04';
                }

                $response = Http::withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta'    => implode(',', $betaHeaders),
                    'content-type'      => 'application/json',
                ])
                    ->timeout(600)
                    ->post(self::API_URL, $body);

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

    public function checkRateLimit(int $organizationId): void
    {
        $key = "anthropic:{$organizationId}";

        if (RateLimiter::tooManyAttempts($key, $this->requestsPerMinute)) {
            $waitSeconds = RateLimiter::availableIn($key) + 1;
            Log::info("[ANTHROPIC] Rate limit org #{$organizationId} — attesa {$waitSeconds}s");
            sleep($waitSeconds);
        }

        RateLimiter::hit($key, 60);
    }

    // ── Usage tracking wrappers ───────────────────────────────────
    // Mantengono la signature originale di call()/callCached() per
    // retrocompatibilità. I caller che vogliono trackare i costi usano
    // questi wrapper che ritornano response + UsageRecord.

    /**
     * @return array{response: array<string, mixed>, usage: UsageRecord}
     */
    public function callWithUsage(
        string  $prompt,
        int     $maxTokens,
        string  $model = 'claude-opus-4-8',
        ?string $systemPrompt = null,
        ?string $purpose = null,
        array   $mcpServers = [],
    ): array {
        $response = $this->call($prompt, $maxTokens, $model, $systemPrompt, $mcpServers);
        $usage    = app(UsageCostCalculator::class)->fromAnthropic($response, $model, $purpose);
        return ['response' => $response, 'usage' => $usage];
    }

    /**
     * @return array{response: array<string, mixed>, usage: UsageRecord}
     */
    public function callCachedWithUsage(
        string  $staticContent,
        string  $dynamicContent,
        int     $maxTokens,
        string  $model,
        ?string $systemPrompt = null,
        ?string $secondStaticContent = null,
        ?string $purpose = null,
        array   $mcpServers = [],
    ): array {
        $response = $this->callCached(
            $staticContent, $dynamicContent, $maxTokens, $model, $systemPrompt, $secondStaticContent, $mcpServers
        );
        $usage = app(UsageCostCalculator::class)->fromAnthropic($response, $model, $purpose);
        return ['response' => $response, 'usage' => $usage];
    }

    /**
     * Trasforma il formato interno (effectiveMcpServers dal Campaign model)
     * nel formato richiesto da Anthropic Messages API per il campo mcp_servers.
     *
     * Input shape:  [{name, url, api_key?}]
     * Output shape: [{type:'url', url, name, authorization_token?}]
     *
     * Item invalidi (manca url o name) sono filtrati silenziosamente.
     *
     * @param  array<int, array{name?: string, url?: string, api_key?: ?string}>  $mcpServers
     * @return list<array{type: string, url: string, name: string, authorization_token?: string}>
     */
    private function formatMcpServers(array $mcpServers): array
    {
        $out = [];
        foreach ($mcpServers as $mcp) {
            $url  = $mcp['url']  ?? null;
            $name = $mcp['name'] ?? null;
            if (! is_string($url) || $url === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $config = [
                'type' => 'url',
                'url'  => $url,
                'name' => $name,
            ];
            $key = $mcp['api_key'] ?? null;
            if (is_string($key) && $key !== '') {
                $config['authorization_token'] = $key;
            }
            $out[] = $config;
        }

        return $out;
    }
}
