<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client Laravel per il microservizio Playwright.
 * Chiama http://localhost:3099/analyze e ritorna i dati grezzi.
 *
 * Se il servizio non è disponibile (disabled o down), ritorna null
 * senza bloccare il pipeline — il job continua con i dati statici.
 */
final class PlaywrightClient
{
    private string $baseUrl;
    private int    $timeout;
    private bool   $enabled;

    public function __construct()
    {
        $this->baseUrl = config('services.playwright.url', 'http://127.0.0.1:3099');
        $this->timeout = (int) config('services.playwright.timeout', 45);
        $this->enabled = (bool) config('services.playwright.enabled', true);
    }

    /**
     * Analizza un URL con Playwright.
     * Ritorna null se il servizio non è disponibile o disabled.
     */
    public function analyze(string $url, bool $includeMobile = true): ?array
    {
        if (! $this->enabled) {
            Log::info('[PLAYWRIGHT] Service disabled, skipping.');
            return null;
        }

        if (! $this->isHealthy()) {
            Log::warning('[PLAYWRIGHT] Service not reachable, falling back to static analysis.');
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/analyze", [
                    'url'     => $url,
                    'options' => [
                        'mobile'       => $includeMobile,
                        'timeout'      => ($this->timeout - 5) * 1000, // ms per Playwright
                        'extract_text' => true,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('[PLAYWRIGHT] Non-200 response: ' . $response->status());
                return null;
            }

            $data = $response->json();

            if (! empty($data['error'])) {
                Log::warning("[PLAYWRIGHT] Analysis error for {$url}: " . $data['error']);
                // Ritorna comunque i dati parziali se ci sono
                return empty($data['screenshots']) ? null : $data;
            }

            Log::info("[PLAYWRIGHT] Analysis complete for {$url}");
            return $data;

        } catch (\Throwable $e) {
            Log::error("[PLAYWRIGHT] Client error: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Verifica che il servizio sia raggiungibile.
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
