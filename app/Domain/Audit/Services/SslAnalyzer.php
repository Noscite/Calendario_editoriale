<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analizza configurazione SSL/HTTPS tramite SSL Labs API.
 *
 * API gratuita, no autenticazione richiesta.
 * Endpoint: https://api.ssllabs.com/api/v3/analyze
 *
 * NOTA: la prima analisi richiede 60-90s (SSL Labs fa il test reale).
 * Usiamo fromCache=on per ottenere risultati dalla cache quando disponibili
 * — tipicamente entro 24h dal precedente test.
 */
final class SslAnalyzer
{
    private const API_URL    = 'https://api.ssllabs.com/api/v3/analyze';
    private const MAX_WAIT   = 90;  // secondi massimi di attesa
    private const POLL_EVERY = 10;  // secondi tra un poll e l'altro

    public function analyze(string $url): array
    {
        if (! config('services.ssllabs.enabled', true)) {
            return $this->emptyResult('SSL Labs disabled');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return $this->emptyResult('URL non valido');
        }

        // Se HTTP (non HTTPS) → fail immediato senza chiamare SSL Labs
        if (str_starts_with($url, 'http://')) {
            return [
                'score'          => 0,
                'rating'         => 'F',
                'has_https'      => false,
                'cert_valid'     => false,
                'cert_expiry'    => null,
                'cert_days_left' => null,
                'protocol'       => null,
                'issues'         => [[
                    'severity' => 'critical',
                    'area'     => 'SSL/HTTPS',
                    'message'  => 'Il sito non usa HTTPS. Google penalizza i siti HTTP e i browser mostrano "Non sicuro".',
                    'action'   => 'Installare un certificato SSL (gratuito con Let\'s Encrypt) e forzare redirect HTTP→HTTPS.',
                ]],
                'error' => null,
            ];
        }

        Log::info("[SSL] Analyzing: {$host}");

        try {
            $data = $this->fetchWithPolling($host);

            if (! $data) {
                return $this->emptyResult('Timeout o errore SSL Labs API');
            }

            return $this->parseResult($data);

        } catch (\Throwable $e) {
            Log::warning("[SSL] Analysis failed for {$host}: {$e->getMessage()}");
            return $this->emptyResult($e->getMessage());
        }
    }

    private function fetchWithPolling(string $host): ?array
    {
        $startTime = time();

        // Prima chiamata: prova dalla cache (maxAge 24h)
        $response = Http::timeout(15)->get(self::API_URL, [
            'host'      => $host,
            'fromCache' => 'on',
            'maxAge'    => '24',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        // Se il test è già in cache e completo → ritorna subito
        if (($data['status'] ?? '') === 'READY') {
            return $data;
        }

        // Non in cache: avvia nuovo test
        $response = Http::timeout(15)->get(self::API_URL, [
            'host'     => $host,
            'startNew' => 'on',
        ]);

        if (! $response->successful()) {
            return null;
        }

        // Poll fino a completamento o timeout
        while (time() - $startTime < self::MAX_WAIT) {
            sleep(self::POLL_EVERY);

            $response = Http::timeout(15)->get(self::API_URL, ['host' => $host]);
            if (! $response->successful()) break;

            $data = $response->json();
            if (($data['status'] ?? '') === 'READY') {
                return $data;
            }

            // ERROR o DNS_ERROR → esci subito
            if (str_starts_with($data['status'] ?? '', 'ERROR')
                || str_starts_with($data['status'] ?? '', 'DNS')) {
                Log::warning("[SSL] SSL Labs error status: " . ($data['status'] ?? ''));
                return null;
            }

            Log::info("[SSL] Still analyzing {$host}: " . ($data['status'] ?? 'unknown'));
        }

        return null;
    }

    private function parseResult(array $data): array
    {
        // Prendi il risultato del primo endpoint (IPv4)
        $endpoint = $data['endpoints'][0] ?? null;
        $grade    = $endpoint['grade'] ?? null;
        $details  = $endpoint['details'] ?? [];

        // Certificato: prova sia il vecchio campo che il nuovo formato certChains
        $cert = $details['cert']
            ?? ($details['certChains'][0]['certs'][0] ?? null);

        $certExpiry   = null;
        $certDaysLeft = null;

        if ($cert) {
            $notAfter = $cert['notAfter'] ?? null;
            if ($notAfter) {
                // notAfter è in millisecondi
                $expiryTs     = (int) ($notAfter / 1000);
                $certExpiry   = date('Y-m-d', $expiryTs);
                $certDaysLeft = (int) round(($expiryTs - time()) / 86400);
            }
        }

        $score  = $this->gradeToScore($grade);
        $issues = $this->buildIssues($grade, $certDaysLeft, $details);

        Log::info("[SSL] Result: grade={$grade}, score={$score}, cert_days={$certDaysLeft}");

        return [
            'score'          => $score,
            'rating'         => $grade ?? 'N/D',
            'has_https'      => true,
            'cert_valid'     => ($certDaysLeft ?? 0) > 0,
            'cert_expiry'    => $certExpiry,
            'cert_days_left' => $certDaysLeft,
            'protocol'       => $details['protocols'][0]['name'] ?? null,
            'issues'         => $issues,
            'error'          => null,
        ];
    }

    private function gradeToScore(?string $grade): int
    {
        return match($grade) {
            'A+'    => 100,
            'A'     => 90,
            'A-'    => 82,
            'B'     => 65,
            'C'     => 45,
            'D'     => 25,
            'E'     => 15,
            'F'     => 0,
            'T'     => 20,   // certificato non fidato
            'M'     => 10,   // certificate name mismatch
            default => 50,
        };
    }

    private function buildIssues(?string $grade, ?int $certDaysLeft, array $details): array
    {
        $issues = [];

        if ($grade && in_array($grade, ['C', 'D', 'E', 'F', 'T', 'M'])) {
            $issues[] = [
                'severity' => 'critical',
                'area'     => 'SSL/HTTPS',
                'message'  => "Configurazione SSL insufficiente: rating {$grade}. Possibili vulnerabilità di sicurezza.",
                'action'   => 'Aggiornare la configurazione TLS del server. Disabilitare protocolli obsoleti (TLS 1.0, 1.1). Verificare la catena di certificati.',
            ];
        } elseif ($grade === 'B') {
            $issues[] = [
                'severity' => 'warning',
                'area'     => 'SSL/HTTPS',
                'message'  => 'Configurazione SSL discreta ma migliorabile (rating B).',
                'action'   => 'Ottimizzare la configurazione TLS per ottenere rating A o A+. Verificare cipher suites abilitate.',
            ];
        }

        if ($certDaysLeft !== null && $certDaysLeft < 30) {
            $issues[] = [
                'severity' => $certDaysLeft < 7 ? 'critical' : 'warning',
                'area'     => 'SSL/HTTPS',
                'message'  => "Certificato SSL in scadenza tra {$certDaysLeft} giorni.",
                'action'   => 'Rinnovare immediatamente il certificato SSL. Con Let\'s Encrypt il rinnovo è automatico se configurato correttamente.',
            ];
        }

        return $issues;
    }

    private function emptyResult(string $reason): array
    {
        return [
            'score' => null, 'rating' => null, 'has_https' => null,
            'cert_valid' => null, 'cert_expiry' => null, 'cert_days_left' => null,
            'protocol' => null, 'issues' => [], 'error' => $reason,
        ];
    }
}
