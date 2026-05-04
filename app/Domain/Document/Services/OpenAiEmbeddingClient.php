<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandApiKeyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client HTTP per l'API OpenAI Embeddings.
 *
 * Usato dal pipeline KB (ProcessDocumentJob) per generare embedding
 * sui chunk dei documenti brand. Modello di default: text-embedding-3-small
 * (1536 dim) — allineato allo schema pgvector di document_chunks.
 */
class OpenAiEmbeddingClient
{
    private const API_URL    = 'https://api.openai.com/v1/embeddings';
    private const MAX_TRIES  = 3;
    private const MAX_BATCH  = 100;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openai.api_key', '');
    }

    public function withBrand(?Brand $brand): static
    {
        if ($brand) {
            $key = app(BrandApiKeyService::class)->getWithSuperAdminFallback(
                $brand,
                BrandApiKeyService::OPENAI_API_KEY,
                'services.openai.api_key'
            );
            $clone         = clone $this;
            $clone->apiKey = $key;
            return $clone;
        }
        return $this;
    }

    /**
     * Genera embedding per un array di testi.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>  vettori float nello stesso ordine dell'input
     */
    public function embed(array $texts, string $model = 'text-embedding-3-small'): array
    {
        if ($texts === []) {
            return [];
        }

        if (count($texts) > self::MAX_BATCH) {
            $result = [];
            foreach (array_chunk($texts, self::MAX_BATCH) as $batch) {
                $result = array_merge($result, $this->embedBatch($batch, $model));
            }
            return $result;
        }

        return $this->embedBatch($texts, $model);
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    private function embedBatch(array $texts, string $model): array
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_TRIES; $attempt++) {
            if ($attempt > 0) {
                $backoffSeconds = (int) pow(2, $attempt);
                Log::info("[OPENAI-EMB] Retry {$attempt}/" . (self::MAX_TRIES - 1) . " — attesa {$backoffSeconds}s");
                sleep($backoffSeconds);
            }

            try {
                $start = microtime(true);

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                    ->timeout(120)
                    ->post(self::API_URL, [
                        'model' => $model,
                        'input' => array_values($texts),
                    ]);

                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? pow(2, $attempt + 1));
                    Log::warning("[OPENAI-EMB] Rate limit 429 — attesa {$retryAfter}s", ['attempt' => $attempt]);
                    sleep(max(1, $retryAfter));
                    continue;
                }

                if ($response->failed()) {
                    $body          = $response->body();
                    $lastException = new RuntimeException("OpenAI Embeddings error: HTTP {$response->status()} — {$body}");
                    Log::error("[OPENAI-EMB] HTTP {$response->status()}", ['body' => $body, 'attempt' => $attempt]);
                    continue;
                }

                $data = $response->json();
                $rows = $data['data'] ?? [];

                $vectors = [];
                foreach ($rows as $row) {
                    $vectors[(int) ($row['index'] ?? count($vectors))] = $row['embedding'] ?? [];
                }
                ksort($vectors);
                $vectors = array_values($vectors);

                $latencyMs = (int) round((microtime(true) - $start) * 1000);
                $totalTokens = $data['usage']['total_tokens'] ?? 0;
                Log::info('[OPENAI-EMB] OK', [
                    'count'        => count($texts),
                    'model'        => $model,
                    'latency_ms'   => $latencyMs,
                    'total_tokens' => $totalTokens,
                ]);

                return $vectors;

            } catch (\Throwable $e) {
                $lastException = $e;
                Log::error("[OPENAI-EMB] Eccezione tentativo {$attempt}", ['error' => $e->getMessage()]);
            }
        }

        throw $lastException ?? new RuntimeException('OpenAI Embeddings: tutti i tentativi falliti');
    }
}
