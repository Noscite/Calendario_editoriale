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

    it('tutti i campi brand+project popolati: ogni fonte appare nella sezione', function () {
        $brandInfo = [
            'sector'                => 'Formazione AI per PMI',
            'description'           => 'Noscite Atheneum forma PMI italiane su AI applicata.',
            'tone_of_voice'         => 'autorevole ma accessibile',
            'brand_values'          => ['etica', 'concretezza', 'rigore scientifico'],
            'target_audience'       => 'CEO e CTO di PMI italiane 30-150 dipendenti',
            'unique_selling_points' => 'Approccio etico verificato, percorsi modulari',
            'voice_examples'        => [],
            'style_guide'           => 'Frasi brevi, esempi concreti italiani, mai anglicismi gratuiti.',
        ];
        $projectInfo = [
            'brief'         => 'Lancio piano editoriale Q2 con focus su AI Act e adozione PMI',
            'objectives'    => ['lead_generation', 'brand_awareness'],
            'custom_prompt' => 'I corsi disponibili sono PRIMUS, CONSILIUM, INITIUM, STRUCTURA, AI AGENTS & MCP. Vietato citare altri corsi.',
            'special_dates' => [
                ['date' => '2026-05-15', 'description' => 'Workshop AI Act alla XYZ Spa'],
                ['date' => '2026-05-28', 'description' => 'Pubblicazione libro "AI Etica per PMI"'],
            ],
            'competitors'   => ['CompetitorAlfa', 'CompetitorBeta SRL', 'AcademyOmega'],
        ];

        $prompt = $this->builder->buildStrategyPrompt(
            brandName:    'Stefano Andrello / Noscite Atheneum',
            brandInfo:    $brandInfo,
            projectInfo:  $projectInfo,
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

        // Header + REGOLA OPERATIVA presenti
        expect($prompt)->toContain('## ELEMENTI DEL BRAND DISPONIBILI');

        // Fonti 1-2 (pointer alle sezioni esistenti)
        expect($prompt)->toContain('Sezione ## BRAND sopra')
            ->and($prompt)->toContain('Sezione ## KNOWLEDGE BASE sopra');

        // Fonti 3-5 (legacy)
        expect($prompt)->toContain('3. Unique selling points: Approccio etico verificato')
            ->and($prompt)->toContain('4. Brief del progetto: Lancio piano editoriale Q2')
            ->and($prompt)->toContain('5. Descrizione brand (riepilogo): Noscite Atheneum forma');

        // Fonti 6-9 (nuovi campi brand)
        expect($prompt)->toContain('6. Style guide del brand: Frasi brevi, esempi concreti italiani')
            ->and($prompt)->toContain('7. Valori del brand: etica, concretezza, rigore scientifico')
            ->and($prompt)->toContain('8. Audience target: CEO e CTO di PMI italiane')
            ->and($prompt)->toContain('9. Tono di voce: autorevole ma accessibile');

        // Fonte 10 (custom_prompt — primario per liste corsi/libri caso 649)
        expect($prompt)->toContain('10. Istruzioni custom del progetto:')
            ->and($prompt)->toContain('PRIMUS, CONSILIUM, INITIUM, STRUCTURA, AI AGENTS & MCP');

        // Fonte 11 (special_dates come bullet list di fatti verificabili)
        expect($prompt)->toContain('11. Eventi/date specifiche del progetto')
            ->and($prompt)->toContain('FATTI VERIFICABILI')
            ->and($prompt)->toContain('Data evento 2026-05-15: Workshop AI Act alla XYZ Spa')
            ->and($prompt)->toContain('Data evento 2026-05-28: Pubblicazione libro "AI Etica per PMI"');

        // Sub-sezione "ASSET DA NON CITARE" con i competitor
        expect($prompt)->toContain('## ASSET DA NON CITARE')
            ->and($prompt)->toContain('Competitor da NON menzionare: CompetitorAlfa, CompetitorBeta SRL, AcademyOmega');
    });

    it('campi vuoti graceful: solo description+brief popolati, sezioni assenti omesse', function () {
        $brandInfo = [
            'sector'      => 'Generic',
            'description' => 'Brand minimale.',
            // tutto il resto null/vuoto
            'tone_of_voice'         => null,
            'brand_values'          => null,
            'target_audience'       => null,
            'unique_selling_points' => null,
            'style_guide'           => null,
            'voice_examples'        => [],
        ];
        $projectInfo = [
            'brief'         => 'Brief minimale.',
            'custom_prompt' => null,
            'special_dates' => [],
            'competitors'   => [],
            'objectives'    => [],
        ];

        $prompt = $this->builder->buildStrategyPrompt(
            brandName:    'Minimal',
            brandInfo:    $brandInfo,
            projectInfo:  $projectInfo,
            startDate:    Carbon::parse('2026-05-01'),
            endDate:      Carbon::parse('2026-05-15'),
            platforms:    ['linkedin'],
            postsPerWeek: ['linkedin' => 3],
            themes:       ['T1'],
            urlContext:   null,
            ragContext:   '',
            buyerPersonas: [],
            contentMixData: [],
        );

        // Header e fonte 4 (brief) e 5 (descrizione) presenti
        expect($prompt)->toContain('## ELEMENTI DEL BRAND DISPONIBILI')
            ->and($prompt)->toContain('4. Brief del progetto: Brief minimale.')
            ->and($prompt)->toContain('5. Descrizione brand (riepilogo): Brand minimale.');

        // Fonti per campi non popolati: NON devono apparire
        expect($prompt)->not->toContain('3. Unique selling points:')
            ->and($prompt)->not->toContain('6. Style guide del brand:')
            ->and($prompt)->not->toContain('7. Valori del brand:')
            ->and($prompt)->not->toContain('8. Audience target:')
            ->and($prompt)->not->toContain('9. Tono di voce:')
            ->and($prompt)->not->toContain('10. Istruzioni custom del progetto:')
            ->and($prompt)->not->toContain('11. Eventi/date specifiche');

        // Sub-sezione "ASSET DA NON CITARE": NON deve apparire (no competitors)
        expect($prompt)->not->toContain('## ASSET DA NON CITARE')
            ->and($prompt)->not->toContain('Competitor da NON menzionare');

        // Niente errori, regola operativa e vincoli sono comunque presenti
        expect($prompt)->toContain('REGOLA OPERATIVA')
            ->and($prompt)->toContain('VINCOLO 1 — Aderenza assoluta al brand kit');
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
