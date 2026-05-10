<?php

declare(strict_types=1);

namespace App\Domain\Brand\Services;

use App\Domain\Brand\Models\Brand;

/**
 * Calcola lo score di completezza del Brand (0-100) per il wizard PR-1.
 *
 * Mappa pesi (totale 100):
 *   - identity         15  (name + description ≥80 char + sector)
 *   - voice            20  (tone_of_voice + voice_examples ≥3 con content ≥20 char)
 *   - narrative_assets 25  (narrative_assets ≥1 entry valida; founder è bonus, non condizione)
 *   - usp_pillars      25  (split: USP+brand_values=10 / default_content_pillars≥4=15)
 *   - kb               15  (almeno 1 BrandDocument associato)
 *
 * Politica: all-or-nothing per sotto-sezione (USP+values insieme, pillars
 * separato). Niente pro-rata sul singolo pillar/example.
 *
 * Output:
 * {
 *   "score": int,
 *   "threshold": 70,
 *   "can_generate": bool,
 *   "sections": { "<key>": { "weight", "earned", "complete", "label" } },
 *   "missing": [ { "section", "label" } ]
 * }
 */
final class BrandCompletenessService
{
    public const THRESHOLD = 70;

    public const WEIGHTS = [
        'identity'         => 15,
        'voice'            => 20,
        'narrative_assets' => 25,
        'usp_pillars'      => 25,
        'kb'               => 15,
    ];

    /** @return array<string, mixed> */
    public function score(Brand $brand): array
    {
        $missing = [];

        // ── identity (15) ────────────────────────────────────────────
        $hasName        = is_string($brand->name) && trim($brand->name) !== '';
        $hasDescription = is_string($brand->description) && mb_strlen(trim($brand->description)) >= 80;
        $hasSector      = is_string($brand->sector) && trim($brand->sector) !== '';
        $identityComplete = $hasName && $hasDescription && $hasSector;
        $identityEarned   = $identityComplete ? self::WEIGHTS['identity'] : 0;
        if (! $identityComplete) {
            if (! $hasName)        $missing[] = ['section' => 'identity', 'label' => 'Identità: nome del brand mancante'];
            if (! $hasDescription) $missing[] = ['section' => 'identity', 'label' => 'Identità: descrizione mancante o sotto 80 caratteri'];
            if (! $hasSector)      $missing[] = ['section' => 'identity', 'label' => 'Identità: settore mancante'];
        }

        // ── voice (20) ───────────────────────────────────────────────
        $hasTone = is_string($brand->tone_of_voice) && trim($brand->tone_of_voice) !== '';
        $voiceExamples = $brand->voice_examples;
        $validExamples = is_array($voiceExamples)
            ? array_filter($voiceExamples, fn ($e) => is_array($e)
                && isset($e['content'])
                && is_string($e['content'])
                && mb_strlen(trim($e['content'])) >= 20)
            : [];
        $voiceComplete = $hasTone && count($validExamples) >= 3;
        $voiceEarned   = $voiceComplete ? self::WEIGHTS['voice'] : 0;
        if (! $voiceComplete) {
            if (! $hasTone)                 $missing[] = ['section' => 'voice', 'label' => 'Voce: tono di voce mancante'];
            if (count($validExamples) < 3)  $missing[] = ['section' => 'voice', 'label' => "Voce: servono min 3 esempi (ora " . count($validExamples) . ')'];
        }

        // ── narrative_assets (25) ────────────────────────────────────
        $assets = is_array($brand->narrative_assets) ? $brand->narrative_assets : [];
        $validAssets = array_filter($assets, fn ($a) => is_array($a)
            && isset($a['type']) && is_string($a['type']) && trim($a['type']) !== ''
            && isset($a['name']) && is_string($a['name']) && trim($a['name']) !== '');
        $assetsComplete = count($validAssets) >= 1;
        $assetsEarned   = $assetsComplete ? self::WEIGHTS['narrative_assets'] : 0;
        if (! $assetsComplete) {
            $missing[] = ['section' => 'narrative_assets', 'label' => 'Asset narrativi: serve min 1 voce valida (corso/libro/prodotto/evento/partner/servizio/luogo/tradizione)'];
        }

        // ── usp_pillars (25 = 10 USP+values  +  15 pillars) ──────────
        $hasUsp    = is_string($brand->unique_selling_points) && trim($brand->unique_selling_points) !== '';
        $bvCount   = $this->countBrandValues($brand->brand_values);
        $valuesOk  = $bvCount >= 2;
        $uspBlock  = $hasUsp && $valuesOk;       // 10pt all-or-nothing su questo blocco

        $pillars = is_array($brand->default_content_pillars) ? $brand->default_content_pillars : [];
        $validPillars = array_filter($pillars, fn ($p) => is_array($p)
            && isset($p['name']) && is_string($p['name']) && trim($p['name']) !== '');
        $pillarsBlock = count($validPillars) >= 4 && count($validPillars) <= 6; // 15pt all-or-nothing

        $uspPillarsEarned = ($uspBlock ? 10 : 0) + ($pillarsBlock ? 15 : 0);
        $uspPillarsComplete = $uspPillarsEarned === self::WEIGHTS['usp_pillars'];
        if (! $uspBlock) {
            if (! $hasUsp)   $missing[] = ['section' => 'usp_pillars', 'label' => 'USP: differenziatori del brand mancanti'];
            if (! $valuesOk) $missing[] = ['section' => 'usp_pillars', 'label' => "Valori: servono min 2 brand_values (ora {$bvCount})"];
        }
        if (! $pillarsBlock) {
            $missing[] = ['section' => 'usp_pillars', 'label' => 'Pillar: serve min 4 default_content_pillars (max 6)'];
        }

        // ── kb (15) ──────────────────────────────────────────────────
        $kbCount = $brand->documents()->count();
        $kbComplete = $kbCount >= 1;
        $kbEarned   = $kbComplete ? self::WEIGHTS['kb'] : 0;
        if (! $kbComplete) {
            $missing[] = ['section' => 'kb', 'label' => 'Knowledge base: nessun documento caricato'];
        }

        $score = $identityEarned + $voiceEarned + $assetsEarned + $uspPillarsEarned + $kbEarned;

        return [
            'score'        => $score,
            'threshold'    => self::THRESHOLD,
            'can_generate' => $score >= self::THRESHOLD,
            'sections' => [
                'identity'         => ['weight' => self::WEIGHTS['identity'],         'earned' => $identityEarned,    'complete' => $identityComplete,   'label' => 'Identità'],
                'voice'            => ['weight' => self::WEIGHTS['voice'],            'earned' => $voiceEarned,       'complete' => $voiceComplete,      'label' => 'Voce'],
                'narrative_assets' => ['weight' => self::WEIGHTS['narrative_assets'], 'earned' => $assetsEarned,      'complete' => $assetsComplete,     'label' => 'Asset narrativi'],
                'usp_pillars'      => ['weight' => self::WEIGHTS['usp_pillars'],      'earned' => $uspPillarsEarned,  'complete' => $uspPillarsComplete, 'label' => 'USP & Pillar'],
                'kb'               => ['weight' => self::WEIGHTS['kb'],               'earned' => $kbEarned,          'complete' => $kbComplete,         'label' => 'Knowledge base'],
            ],
            'missing' => array_values($missing),
        ];
    }

    /**
     * Conta brand_values come "lista" indipendentemente dalla shape (string,
     * array di stringhe, array di oggetti, KeyValue dict).
     */
    private function countBrandValues(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_string($value)) {
            $parts = array_filter(array_map('trim', explode(',', $value)), fn ($s) => $s !== '');
            return count($parts);
        }
        if (! is_array($value)) {
            return 0;
        }
        return count(array_filter($value, function ($v) {
            if (is_string($v)) return trim($v) !== '';
            if (is_array($v)) {
                foreach (['name', 'value', 'label', 'title'] as $k) {
                    if (isset($v[$k]) && is_string($v[$k]) && trim($v[$k]) !== '') return true;
                }
            }
            return false;
        }));
    }
}
