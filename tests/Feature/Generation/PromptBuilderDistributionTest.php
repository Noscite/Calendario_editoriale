<?php

declare(strict_types=1);

use App\Domain\Generation\Services\PromptBuilder;
use Carbon\Carbon;

/**
 * Verifica i vincoli HARD di distribuzione per piattaforma.
 *
 * Il prompt non deve più passare i ratio "post per settimana" come riga
 * informativa (che il modello ignorava, con bias verso Instagram), ma totali
 * assoluti per piattaforma calcolati sul periodo del progetto:
 *
 *   totale = round(perWeek * totalWeeks),  totalWeeks = max(1, diffInDays / 7)
 *
 * Periodo di 30 giorni → totalWeeks = 30/7 = 4.2857:
 *   - Instagram (2/sett) → round(8.571)  = 9
 *   - Facebook  (3/sett) → round(12.857) = 13
 *   - LinkedIn  (2/sett) → round(8.571)  = 9
 *   - Google Business Profile (2/sett)   = 9
 */
describe('PromptBuilder hard distribution constraints', function () {

    beforeEach(function () {
        $this->builder = new PromptBuilder();
        // 2026-01-01 → 2026-01-31 = 30 giorni di differenza.
        $this->startDate = Carbon::parse('2026-01-01');
        $this->endDate   = Carbon::parse('2026-01-31');
        $this->postsPerWeek = ['ig' => 2, 'fb' => 3, 'li' => 2, 'gbp' => 2];
    });

    it('buildBatchPrompt emette totali assoluti esatti per piattaforma', function () {
        $prompt = $this->builder->buildBatchPrompt(
            brandName:     'Acme SRL',
            brandInfo:     ['sector' => 'Tech', 'description' => 'Software', 'tone_of_voice' => 'professionale', 'brand_values' => []],
            projectInfo:   ['brief' => 'Test brief', 'objectives' => ['brand_awareness']],
            startDate:     $this->startDate,
            endDate:       $this->endDate,
            platforms:     ['ig', 'fb', 'li', 'gbp'],
            postsPerWeek:  $this->postsPerWeek,
            themes:        ['tema'],
            urlContext:    null,
            ragContext:    '',
            styleGuide:    '',
            buyerPersonas: ['personas' => [], 'scheduling_strategy' => []],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('DISTRIBUZIONE OBBLIGATORIA')
            ->toContain('Instagram: ESATTAMENTE 9')
            ->toContain('Facebook: ESATTAMENTE 13')
            ->toContain('LinkedIn: ESATTAMENTE 9')
            ->toContain('Google Business Profile: ESATTAMENTE 9')
            ->toContain('TOTALE: 40 post')
            ->toContain('Deviazione max ±5%')
            // La vecchia riga informativa non deve più comparire.
            ->not->toContain('Post per settimana:');
    });

    it('buildStrategyPrompt emette gli stessi totali assoluti', function () {
        $prompt = $this->builder->buildStrategyPrompt(
            brandName:     'Acme SRL',
            brandInfo:     ['sector' => 'Tech', 'description' => 'Software', 'tone_of_voice' => 'professionale', 'brand_values' => []],
            projectInfo:   ['brief' => 'Test brief', 'objectives' => ['brand_awareness']],
            startDate:     $this->startDate,
            endDate:       $this->endDate,
            platforms:     ['ig', 'fb', 'li', 'gbp'],
            postsPerWeek:  $this->postsPerWeek,
            themes:        ['tema'],
            urlContext:    null,
            ragContext:    '',
            buyerPersonas: ['personas' => [], 'scheduling_strategy' => []],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('Instagram: ESATTAMENTE 9')
            ->toContain('Facebook: ESATTAMENTE 13')
            ->toContain('LinkedIn: ESATTAMENTE 9')
            ->toContain('Google Business Profile: ESATTAMENTE 9')
            ->not->toContain('Post per settimana:');
    });
});
