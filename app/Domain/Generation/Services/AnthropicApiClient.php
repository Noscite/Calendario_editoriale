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

    public function call(
        string  $prompt,
        int     $maxTokens,
        string  $model = 'claude-sonnet-4-6',
        ?string $systemPrompt = null,
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
                $response = Http::withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                    ->timeout(600)
                    ->post(self::API_URL, [
                        'model'      => $model,
                        'max_tokens' => $maxTokens,
                        'system'     => $system,
                        'messages'   => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
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

                return $response->json();

            } catch (\Throwable $e) {
                $lastException = $e;
                Log::error("[ANTHROPIC] Eccezione tentativo {$attempt}", ['error' => $e->getMessage()]);
            }
        }

        throw $lastException ?? new RuntimeException('Claude API: tutti i tentativi falliti');
    }

    public function callCached(
        string  $staticContent,
        string  $dynamicContent,
        int     $maxTokens,
        string  $model,
        ?string $systemPrompt = null,
        ?string $secondStaticContent = null,
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

                $response = Http::withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta'    => 'prompt-caching-2024-07-31',
                    'content-type'      => 'application/json',
                ])
                    ->timeout(600)
                    ->post(self::API_URL, [
                        'model'      => $model,
                        'max_tokens' => $maxTokens,
                        'system'     => $systemBlocks,
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
        string  $model = 'claude-sonnet-4-6',
        ?string $systemPrompt = null,
        ?string $purpose = null,
    ): array {
        $response = $this->call($prompt, $maxTokens, $model, $systemPrompt);
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
    ): array {
        $response = $this->callCached(
            $staticContent, $dynamicContent, $maxTokens, $model, $systemPrompt, $secondStaticContent
        );
        $usage = app(UsageCostCalculator::class)->fromAnthropic($response, $model, $purpose);
        return ['response' => $response, 'usage' => $usage];
    }
}
