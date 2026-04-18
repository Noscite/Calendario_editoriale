<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analisi neuromarketing visiva tramite Claude Vision.
 *
 * Riceve gli screenshot (base64 JPEG) catturati da PlaywrightClient
 * e chiede a Claude di valutare la pagina come farebbe un esperto
 * di neuromarketing UX, contestualizzato per settore.
 *
 * Output: score 0-100 + analisi strutturata per above-fold desktop e mobile.
 */
final class NeuromarketingVisionAnalyzer
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL   = 'claude-sonnet-4-5';

    /**
     * Criteri di valutazione per settore.
     * Ogni settore ha priorità diverse in termini di neuromarketing.
     */
    private const SECTOR_CRITERIA = [
        'ristorante' => [
            'priority'  => ['foto cibo appetitosa', 'prenotazione facile', 'orari visibili', 'posizione/mappa'],
            'red_flags' => ['nessuna foto', 'prenotazione nascosta', 'menu non accessibile'],
            'benchmark' => 'Il cliente decide in 3 secondi se prenotare — la foto del piatto è il fattore principale.',
        ],
        'studio legale' => [
            'priority'  => ['credenziali visibili', 'aree di specializzazione chiare', 'caso di successo', 'contatto immediato'],
            'red_flags' => ['troppo testo legale', 'nessuna foto del team', 'CTA generica'],
            'benchmark' => 'La fiducia è tutto — foto professionali del team e certificazioni above-fold convertono.',
        ],
        'ecommerce' => [
            'priority'  => ['prodotto above-fold', 'prezzo visibile', 'CTA acquisto', 'trust badge (spedizione gratis, resi facili)'],
            'red_flags' => ['prezzo nascosto', 'CTA debole', 'nessuna prova sociale'],
            'benchmark' => 'LCP deve essere il prodotto — ogni secondo in più = -7% conversioni.',
        ],
        'agenzia' => [
            'priority'  => ['portfolio visibile', 'clienti noti', 'proposta di valore unica', 'case study'],
            'red_flags' => ['linguaggio generico', 'nessun risultato concreto', 'troppi servizi elencati'],
            'benchmark' => 'Il cliente cerca prova di risultati — numeri e loghi clienti convertono più del testo.',
        ],
        'medico' => [
            'priority'  => ['credenziali (laurea, specializzazione)', 'foto professionale', 'servizi chiari', 'prenotazione online'],
            'red_flags' => ['nessuna foto del medico', 'info prezzi assenti', 'CTA generica'],
            'benchmark' => 'La fiducia è il driver principale — ogni elemento di credibilità aumenta le prenotazioni.',
        ],
        'default' => [
            'priority'  => ['proposta di valore chiara', 'CTA principale visibile', 'social proof', 'contatto facile'],
            'red_flags' => ['headline generica', 'nessuna prova sociale', 'CTA nascosta'],
            'benchmark' => 'L\'utente decide in 5 secondi se restare — il headline è l\'elemento più critico.',
        ],
    ];

    public function analyze(
        string  $sector,
        string  $brandName,
        ?string $screenshotDesktop,
        ?string $screenshotMobile,
        array   $brandProfile,
    ): array {
        if (! $screenshotDesktop && ! $screenshotMobile) {
            return $this->emptyResult('Nessuno screenshot disponibile (Playwright non attivo)');
        }

        $apiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));
        if (! $apiKey) {
            return $this->emptyResult('Anthropic API key non configurata');
        }

        Log::info("[NEURO-VISION] Analyzing {$brandName} ({$sector})");

        $criteria = $this->getSectorCriteria($sector);
        $messages = $this->buildMessages(
            $sector, $brandName, $criteria, $brandProfile,
            $screenshotDesktop, $screenshotMobile,
        );

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
                ->timeout(60)
                ->post(self::API_URL, [
                    'model'      => self::MODEL,
                    'max_tokens' => 1500,
                    'system'     => 'Sei un esperto di neuromarketing e UX design specializzato in PMI italiane. Valuti siti web con l\'occhio di un consulente senior. Rispondi SOLO con JSON valido.',
                    'messages'   => $messages,
                ]);

            if (! $response->successful()) {
                Log::error('[NEURO-VISION] Claude API error: ' . $response->status());
                return $this->emptyResult('Errore API Claude');
            }

            $content = $response->json('content.0.text', '');

            // Strip markdown code fences
            $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
            $content = preg_replace('/```\s*$/m', '', $content);
            $content = trim($content);

            // Fix literal control chars inside JSON string values
            $content = preg_replace_callback(
                '/"(?:[^"\\\\]|\\\\.)*"/s',
                fn ($m) => str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], $m[0]),
                $content
            );

            $parsed  = json_decode($content, true);

            if (! $parsed) {
                Log::warning('[NEURO-VISION] JSON parse error. Raw: ' . substr($content, 0, 200));
                return $this->emptyResult('Risposta non parseable');
            }

            Log::info('[NEURO-VISION] Analysis complete — score: ' . ($parsed['score'] ?? 'N/D'));
            return $parsed;

        } catch (\Throwable $e) {
            Log::error("[NEURO-VISION] Exception: {$e->getMessage()}");
            return $this->emptyResult($e->getMessage());
        }
    }

    private function buildMessages(
        string  $sector,
        string  $brandName,
        array   $criteria,
        array   $brandProfile,
        ?string $desktopB64,
        ?string $mobileB64,
    ): array {
        $priorityList = implode(', ', $criteria['priority']);
        $redFlagList  = implode(', ', $criteria['red_flags']);
        $benchmark    = $criteria['benchmark'];

        $brandContext = json_encode([
            'tone_of_voice'   => $brandProfile['tone_of_voice']  ?? 'N/D',
            'brand_values'    => $brandProfile['brand_values']    ?? [],
            'target_audience' => $brandProfile['target_audience'] ?? 'N/D',
            'colors'          => $brandProfile['colors']          ?? 'N/D',
        ], JSON_UNESCAPED_UNICODE);

        $content = [];

        $content[] = [
            'type' => 'text',
            'text' => "Analizza questo sito web per **{$brandName}** (settore: {$sector}).\n\n"
                . "**Profilo brand dichiarato:**\n{$brandContext}\n\n"
                . "**Benchmark settore:** {$benchmark}\n\n"
                . "**Elementi prioritari per questo settore:** {$priorityList}\n"
                . "**Red flag specifici:** {$redFlagList}\n\n"
                . "---\n\n**SCREENSHOT DESKTOP (1440px):**",
        ];

        if ($desktopB64) {
            $content[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'image/jpeg',
                    'data'       => $desktopB64,
                ],
            ];
        }

        if ($mobileB64) {
            $content[] = ['type' => 'text', 'text' => "\n**SCREENSHOT MOBILE (390px iPhone):**"];
            $content[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'image/jpeg',
                    'data'       => $mobileB64,
                ],
            ];
        }

        $content[] = [
            'type' => 'text',
            'text' => <<<PROMPT

---

Valuta la pagina above-fold su questi criteri specifici per il settore "{$sector}":

**1. GERARCHIA VISIVA** — L'occhio viene guidato verso la CTA principale? Segue un pattern logico (F o Z)?
**2. PROPOSTA DI VALORE** — Il messaggio principale è chiaro in <5 secondi per un visitatore freddo?
**3. CTA PRINCIPALE** — È visibile, contrastata, con testo d'azione specifico (non "Scopri di più")?
**4. SOCIAL PROOF** — Testimonianze, loghi clienti, certificazioni, numeri visibili senza scroll?
**5. COERENZA BRAND** — I colori, il tono visivo e le immagini corrispondono al profilo brand dichiarato?
**6. MOBILE UX** (se screenshot disponibile) — Tap targets adeguati? Testo leggibile? CTA raggiungibile col pollice?
**7. ELEMENTI SPECIFICI SETTORE** — Presenza degli elementi prioritari: {$priorityList}
**8. RED FLAG** — Presenza di: {$redFlagList}

Rispondi SOLO con questo JSON:
{
  "score": 72,
  "grade": "B+",
  "above_fold_verdict": "Frase diretta di 2 righe: cosa funziona e cosa manca",
  "hierarchy_score": 70,
  "cta_score": 65,
  "value_prop_score": 75,
  "social_proof_score": 60,
  "brand_coherence_score": 80,
  "mobile_score": 70,
  "sector_elements_found": ["elemento1", "elemento2"],
  "sector_elements_missing": ["elemento_mancante1"],
  "red_flags_found": ["red_flag_se_presente"],
  "strengths": [
    "Punto di forza specifico 1 (cita elemento visivo reale)",
    "Punto di forza specifico 2"
  ],
  "critical_improvements": [
    {
      "element": "CTA principale",
      "current": "Descrizione di quello che vedi ora",
      "suggested": "Cosa dovrebbe esserci invece",
      "impact": "Perché questo impatta le conversioni"
    }
  ],
  "mobile_notes": "Note specifiche sul mobile (se screenshot disponibile, altrimenti null)",
  "sector_specific_notes": "Valutazione contestualizzata al settore {$sector}: cosa manca rispetto al benchmark",
  "error": null
}
PROMPT,
        ];

        return [['role' => 'user', 'content' => $content]];
    }

    private function getSectorCriteria(string $sector): array
    {
        $sectorLower = strtolower(trim($sector));

        foreach (self::SECTOR_CRITERIA as $key => $criteria) {
            if ($key === 'default') continue;
            if (str_contains($sectorLower, $key) || str_contains($key, $sectorLower)) {
                return $criteria;
            }
        }

        $mappings = [
            'food'         => 'ristorante',
            'pizzeria'     => 'ristorante',
            'bar'          => 'ristorante',
            'avvocato'     => 'studio legale',
            'notaio'       => 'studio legale',
            'shop'         => 'ecommerce',
            'negozio'      => 'ecommerce',
            'vendita'      => 'ecommerce',
            'marketing'    => 'agenzia',
            'comunicazione'=> 'agenzia',
            'digitale'     => 'agenzia',
            'dottore'      => 'medico',
            'dentista'     => 'medico',
            'clinica'      => 'medico',
            'psicologo'    => 'medico',
            'fisioterapia' => 'medico',
        ];

        foreach ($mappings as $keyword => $mapped) {
            if (str_contains($sectorLower, $keyword)) {
                return self::SECTOR_CRITERIA[$mapped];
            }
        }

        return self::SECTOR_CRITERIA['default'];
    }

    private function emptyResult(string $reason): array
    {
        return [
            'score'                   => null,
            'grade'                   => null,
            'above_fold_verdict'      => null,
            'hierarchy_score'         => null,
            'cta_score'               => null,
            'value_prop_score'        => null,
            'social_proof_score'      => null,
            'brand_coherence_score'   => null,
            'mobile_score'            => null,
            'sector_elements_found'   => [],
            'sector_elements_missing' => [],
            'red_flags_found'         => [],
            'strengths'               => [],
            'critical_improvements'   => [],
            'mobile_notes'            => null,
            'sector_specific_notes'   => null,
            'error'                   => $reason,
        ];
    }
}
