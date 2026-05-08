<?php

declare(strict_types=1);

use App\Domain\Generation\Services\PromptBuilder;
use Carbon\Carbon;

/**
 * Verifica che il prompt costruito da PromptBuilder per la fase strategy
 * (Opus) e copy (Sonnet) contenga i 3 vincoli anti-invenzione richiesti
 * dal fix #3 (post id 818/828/832/835/837/825 del Project 649):
 *
 *   1. Aderenza al brand kit (no nomi corso inventati)
 *   2. Niente aneddoti specifici fabricati
 *   3. Niente statistiche numeriche non documentate
 *   + eccezione compliance normativa (AI Act / Reg. UE 2024/1689)
 */
describe('PromptBuilder anti-invenzione', function () {

    beforeEach(function () {
        $this->builder = new PromptBuilder();
    });

    it('aderenza brand kit: include i nomi dei corsi forniti + vincolo no invenzione', function () {
        // Brand kit con 3 corsi specifici (mimics il caso del Project 649)
        $brandInfo = [
            'sector'                => 'Formazione AI per PMI',
            'description'           => 'Noscite Atheneum offre i corsi PRIMUS, CONSILIUM, INITIUM per formare PMI italiane sull\'AI.',
            'tone_of_voice'         => 'professionale',
            'brand_values'          => ['etica', 'concretezza'],
            'unique_selling_points' => 'Corsi disponibili: PRIMUS, CONSILIUM, INITIUM. Niente altri corsi al momento.',
            'voice_examples'        => [],
        ];

        $prompt = $this->builder->buildStrategyPrompt(
            brandName:    'Stefano Andrello / Noscite Atheneum',
            brandInfo:    $brandInfo,
            projectInfo:  ['brief' => 'Lancio piano editoriale Q2', 'objectives' => ['lead_generation']],
            startDate:    Carbon::parse('2026-05-01'),
            endDate:      Carbon::parse('2026-05-31'),
            platforms:    ['linkedin', 'facebook'],
            postsPerWeek: ['linkedin' => 3, 'facebook' => 2],
            themes:       ['Frontiera tecnica', 'Pattern operativi'],
            urlContext:   null,
            ragContext:   '',
            buyerPersonas: [],
            contentMixData: [],
        );

        expect($prompt)
            // I 3 corsi reali sono presenti nelle fonti consultabili
            ->toContain('PRIMUS')
            ->toContain('CONSILIUM')
            ->toContain('INITIUM')
            // La sezione esplicita esiste con il label richiesto
            ->toContain('## ELEMENTI DEL BRAND DISPONIBILI')
            ->toContain('UNICA FONTE DI VERITÀ')
            // Il vincolo di non-invenzione è esplicitato
            ->toContain('VINCOLO 1 — Aderenza assoluta al brand kit')
            ->toContain('È vietato inventare nomi nuovi')
            // Le fonti sono enumerate
            ->toContain('## BRAND')
            ->toContain('## KNOWLEDGE BASE');
    });

    it('niente aneddoti: contiene divieto esplicito su "Ieri/Settimana scorsa..."', function () {
        $prompt = $this->builder->buildStrategyPrompt(
            brandName:    'Test Brand',
            brandInfo:    ['sector' => 'Generic'],
            projectInfo:  ['brief' => 'Test brief'],
            startDate:    Carbon::parse('2026-05-01'),
            endDate:      Carbon::parse('2026-05-15'),
            platforms:    ['instagram'],
            postsPerWeek: ['instagram' => 3],
            themes:       ['Tema A'],
            urlContext:   null,
            ragContext:   '',
            buyerPersonas: [],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('VINCOLO 2 — Niente aneddoti specifici inventati')
            ->toContain('Vietato aprire post')
            ->toContain('Ieri / Settimana scorsa / Stamattina')
            ->toContain('Un imprenditore / Un cliente')
            // Costrutti onesti suggeriti
            ->toContain('Una domanda ricorrente nei workshop')
            ->toContain('Capita di sentirsi chiedere');
    });

    it('niente statistiche: contiene divieto esplicito su percentuali/ratio non documentati', function () {
        $prompt = $this->builder->buildStrategyPrompt(
            brandName:    'Test Brand',
            brandInfo:    ['sector' => 'Generic'],
            projectInfo:  ['brief' => 'Test brief'],
            startDate:    Carbon::parse('2026-05-01'),
            endDate:      Carbon::parse('2026-05-15'),
            platforms:    ['linkedin'],
            postsPerWeek: ['linkedin' => 3],
            themes:       ['Tema A'],
            urlContext:   null,
            ragContext:   '',
            buyerPersonas: [],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('VINCOLO 3 — Niente statistiche numeriche inventate')
            ->toContain('Vietato citare percentuali, ratio')
            // Sostituzioni qualitative suggerite
            ->toContain('Una riduzione misurabile')
            ->toContain('Un incremento significativo')
            ->toContain('Diverse aziende');
    });

    it('eccezione normativa: numeri AI Act/Reg. UE devono essere precisi e citati con fonte', function () {
        $prompt = $this->builder->buildStrategyPrompt(
            brandName:    'Test Brand',
            brandInfo:    ['sector' => 'Generic'],
            projectInfo:  ['brief' => 'Test brief'],
            startDate:    Carbon::parse('2026-05-01'),
            endDate:      Carbon::parse('2026-05-15'),
            platforms:    ['linkedin'],
            postsPerWeek: ['linkedin' => 3],
            themes:       ['Tema A'],
            urlContext:   null,
            ragContext:   '',
            buyerPersonas: [],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('ECCEZIONE COMPLIANCE NORMATIVA')
            ->toContain('Regolamento UE 2024/1689')
            ->toContain('DEVONO essere precisi')
            // Fallback se il numero esatto non è noto
            ->toContain('NON lo citi');
    });

    it('reminder anti-invenzione presente nel copy prompt (Sonnet)', function () {
        $parts = $this->builder->buildCopyPromptParts(
            brandName:     'Test Brand',
            brandInfo:     ['sector' => 'Generic', 'description' => 'desc'],
            ragContext:    '',
            strategyPlan:  ['posts' => []],
            batchPosts:    [['scheduled_date' => '2026-05-01', 'platform' => 'linkedin']],
            batchNum:      1,
            totalBatches:  1,
        );

        // Il reminder finisce nella parte 'dynamic' (non cached) prima dell'output
        expect($parts)->toHaveKey('dynamic');
        expect($parts['dynamic'])
            ->toContain('## REMINDER ANTI-INVENZIONE')
            ->toContain('NOMI BRAND')
            ->toContain('ANEDDOTI')
            ->toContain('STATISTICHE')
            ->toContain('Regolamento UE 2024/1689');
    });
});
