<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Services\PromptBuilder;
use Carbon\Carbon;

/**
 * Test per PromptBuilder.
 *
 * Verifica la consistenza e il contenuto dei prompt generati.
 */
describe('PromptBuilder', function () {

    beforeEach(function () {
        $this->builder = new PromptBuilder();
    });

    it('buildBatchPrompt contiene informazioni brand', function () {
        $prompt = $this->builder->buildBatchPrompt(
            brandName:    'Acme SRL',
            brandInfo:    ['sector' => 'Tech', 'description' => 'Software company', 'tone_of_voice' => 'professionale', 'brand_values' => ['innovazione']],
            projectInfo:  ['brief' => 'Test brief', 'objectives' => ['brand_awareness']],
            startDate:    Carbon::parse('2026-03-01'),
            endDate:      Carbon::parse('2026-03-07'),
            platforms:    ['instagram', 'linkedin'],
            postsPerWeek: ['instagram' => 5, 'linkedin' => 3],
            themes:       ['innovazione', 'tech'],
            urlContext:   null,
            ragContext:   '',
            styleGuide:   'Tono professionale',
            buyerPersonas: ['personas' => [], 'scheduling_strategy' => []],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('Acme SRL')
            ->toContain('Tech')
            ->toContain('instagram')
            ->toContain('linkedin')
            ->toContain('2026-03-01')
            ->toContain('2026-03-07')
            ->toContain('JSON array')
            ->not->toBeEmpty();
    });

    it('buildBatchPrompt include la call to action obbligatoria', function () {
        $prompt = $this->builder->buildBatchPrompt(
            brandName:    'Test Brand',
            brandInfo:    [],
            projectInfo:  ['objectives' => ['lead_generation']],
            startDate:    Carbon::parse('2026-03-01'),
            endDate:      Carbon::parse('2026-03-07'),
            platforms:    ['instagram'],
            postsPerWeek: ['instagram' => 3],
            themes:       [],
            urlContext:   null,
            ragContext:   '',
            styleGuide:   '',
            buyerPersonas: [],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('call_to_action')
            ->toContain('OBBLIGATORIO');
    });

    it('buildPersonaPrompt contiene informazioni brand', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, [
            'name'             => 'Brand Test',
            'sector'           => 'Retail',
            'description'      => 'Negozio online',
            'target_audience'  => 'Giovani 18-30',
            'tone_of_voice'    => 'friendly',
        ]);

        $prompt = $this->builder->buildPersonaPrompt($brand, ['instagram', 'facebook'], 'Contenuto sito web');

        expect($prompt)
            ->toContain('Brand Test')
            ->toContain('Retail')
            ->toContain('Giovani 18-30')
            ->toContain('instagram')
            ->toContain('Contenuto sito web')
            ->toContain('scheduling_strategy'); // struttura output JSON
    });

    it('buildRegeneratePrompt contiene il post originale e le istruzioni utente', function () {
        $prompt = $this->builder->buildRegeneratePrompt(
            postContent:     'Contenuto originale del post',
            platform:        'linkedin',
            pillar:          'thought leadership',
            userPrompt:      'Rendilo più formale',
            brandContext:    'Brand — Tech — Software',
            toneOfVoice:     'professionale',
            brandStyleGuide: '',
        );

        expect($prompt)
            ->toContain('Contenuto originale del post')
            ->toContain('linkedin')
            ->toContain('thought leadership')
            ->toContain('Rendilo più formale')
            ->toContain('"content"')
            ->toContain('"hashtags"');
    });

    it('formatSchedulingFromPersonas genera testo per ogni piattaforma', function () {
        $personasData = [
            'scheduling_strategy' => [
                'linkedin' => [
                    'optimal_slots' => [
                        ['day' => 1, 'time' => '08:30', 'priority' => 1],
                        ['day' => 3, 'time' => '12:30', 'priority' => 2],
                    ],
                    'avoid' => ['domenica'],
                ],
                'instagram' => [
                    'optimal_slots' => [
                        ['day' => 5, 'time' => '19:00', 'priority' => 1],
                    ],
                    'avoid' => [],
                ],
            ],
        ];

        $result = $this->builder->formatSchedulingFromPersonas($personasData, ['linkedin', 'instagram']);

        expect($result)
            ->toContain('LINKEDIN')
            ->toContain('INSTAGRAM')
            ->toContain('08:30')
            ->toContain('19:00')
            ->toContain('domenica');
    });

    it('formatContentMixForPrompt restituisce default se array vuoto', function () {
        $result = $this->builder->formatContentMixForPrompt([]);

        expect($result)->toContain('Nessuna ricerca disponibile');
    });

    it('formatContentMixForPrompt include percentuali per piattaforma', function () {
        $contentMixData = [
            'instagram' => [
                'source'                   => 'default',
                'supports_stories'         => true,
                'supports_reels'           => true,
                'recommended_weekly_total' => 7,
                'format_mix'               => ['post_percentage' => 45, 'story_percentage' => 35, 'reel_percentage' => 20],
                'format_weekly_count'      => ['posts' => 3, 'stories' => 3, 'reels' => 1],
                'best_content_ideas'       => ['posts' => [], 'stories' => [], 'reels' => []],
            ],
        ];

        $result = $this->builder->formatContentMixForPrompt($contentMixData);

        expect($result)
            ->toContain('INSTAGRAM')
            ->toContain('45%')
            ->toContain('STORIES')
            ->toContain('REELS');
    });

    it('buildImagePrompt contiene piattaforma e brand', function () {
        $prompt = $this->builder->buildImagePrompt(
            postContent:     'Post su innovazione tecnologica',
            platform:        'linkedin',
            pillar:          'thought leadership',
            brandName:       'TechCorp',
            brandSector:     'Software',
            brandColors:     'Blu e bianco',
            visualSuggestion: 'Immagine moderna',
        );

        expect($prompt)
            ->toContain('linkedin')
            ->toContain('TechCorp')
            ->toContain('Software')
            ->toContain('Blu e bianco')
            ->toContain('DALL-E')
            ->toContain('inglese');
    });

    it('buildBatchPrompt include la sezione voice examples quando presenti', function () {
        $prompt = $this->builder->buildBatchPrompt(
            brandName:   'Acme SRL',
            brandInfo:   [
                'sector'         => 'Tech',
                'description'    => 'Software',
                'tone_of_voice'  => 'professionale',
                'brand_values'   => [],
                'voice_examples' => [
                    ['platform' => 'linkedin', 'content' => 'Esempio post LinkedIn molto autentico del brand', 'note' => 'tono annunci'],
                    ['platform' => 'instagram', 'content' => 'Esempio post Instagram caratteristico del brand qui', 'note' => ''],
                ],
            ],
            projectInfo:    ['brief' => 'b', 'objectives' => ['brand_awareness']],
            startDate:      Carbon::parse('2026-03-01'),
            endDate:        Carbon::parse('2026-03-07'),
            platforms:      ['instagram', 'linkedin'],
            postsPerWeek:   ['instagram' => 3, 'linkedin' => 3],
            themes:         [],
            urlContext:     null,
            ragContext:     '',
            styleGuide:     '',
            buyerPersonas:  ['personas' => [], 'scheduling_strategy' => []],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('## ESEMPI DI VOCE DEL BRAND')
            ->toContain('Esempio 1')
            ->toContain('Esempio post LinkedIn')
            ->toContain('tono annunci');
    });

    it('buildBatchPrompt salta la sezione voice examples quando vuota', function () {
        $prompt = $this->builder->buildBatchPrompt(
            brandName:   'Acme SRL',
            brandInfo:   ['sector' => 'Tech', 'description' => 'X', 'tone_of_voice' => 'pro', 'brand_values' => []],
            projectInfo: ['brief' => 'b', 'objectives' => ['brand_awareness']],
            startDate:   Carbon::parse('2026-03-01'),
            endDate:     Carbon::parse('2026-03-07'),
            platforms:   ['instagram'],
            postsPerWeek: ['instagram' => 3],
            themes:      [],
            urlContext:  null,
            ragContext:  '',
            styleGuide:  '',
            buyerPersonas:  ['personas' => [], 'scheduling_strategy' => []],
            contentMixData: [],
        );

        expect($prompt)->not->toContain('ESEMPI DI VOCE DEL BRAND');
    });

    it('formatVoiceExamplesForPrompt filtra per platform attive nel batch', function () {
        $examples = [
            ['platform' => 'linkedin',  'content' => 'Post LinkedIn xxxxxxxxxxxxxxxxxxxx', 'note' => ''],
            ['platform' => 'facebook',  'content' => 'Post Facebook xxxxxxxxxxxxxxxxxxxx', 'note' => ''],
            ['platform' => 'instagram', 'content' => 'Post Instagram xxxxxxxxxxxxxxxxxxx', 'note' => ''],
        ];

        $section = $this->builder->formatVoiceExamplesForPrompt($examples, ['instagram', 'linkedin']);

        expect($section)
            ->toContain('Post LinkedIn')
            ->toContain('Post Instagram')
            ->not->toContain('Post Facebook');
    });

    it('formatVoiceExamplesForPrompt esclude esempi troppo brevi (<20 chars)', function () {
        $examples = [
            ['platform' => 'linkedin', 'content' => 'troppo breve', 'note' => ''],
            ['platform' => 'linkedin', 'content' => 'questo invece e abbastanza lungo per essere incluso', 'note' => ''],
        ];

        $section = $this->builder->formatVoiceExamplesForPrompt($examples);

        expect($section)
            ->not->toContain('troppo breve')
            ->toContain('abbastanza lungo');
    });

    it('formatVoiceExamplesForPrompt restituisce stringa vuota se nessun esempio valido', function () {
        expect($this->builder->formatVoiceExamplesForPrompt([]))->toBe('');
        expect($this->builder->formatVoiceExamplesForPrompt([
            ['platform' => 'linkedin', 'content' => 'corto', 'note' => ''],
        ]))->toBe('');
    });

    it('buildImagePrompt include orientation hint per Instagram post', function () {
        $prompt = $this->builder->buildImagePrompt(
            postContent: 'Test', platform: 'instagram', pillar: 'lifestyle',
            brandName: 'Test', brandSector: 'food',
        );
        expect($prompt)->toContain('square 1:1 composition');
    });

    it('buildImagePrompt include orientation hint vertical per story/reel', function () {
        $prompt = $this->builder->buildImagePrompt(
            postContent: 'Test', platform: 'instagram', pillar: 'product',
            brandName: 'Test', brandSector: 'fashion',
            contentType: 'story',
        );
        expect($prompt)->toContain('vertical 9:16 composition');

        $promptReel = $this->builder->buildImagePrompt(
            postContent: 'Test', platform: 'instagram', pillar: 'product',
            brandName: 'Test', brandSector: 'fashion',
            contentType: 'reel',
        );
        expect($promptReel)->toContain('vertical 9:16 composition');
    });

    it('buildImagePrompt usa style hint food per settore ristorazione', function () {
        $prompt = $this->builder->buildImagePrompt(
            postContent: 'Test', platform: 'instagram', pillar: 'lifestyle',
            brandName: 'Pizzeria Mario', brandSector: 'pizzeria napoletana',
        );
        expect($prompt)->toContain('close-up food photography');
    });

    it('buildImagePrompt usa style hint sober per settori regolamentati', function () {
        $prompt = $this->builder->buildImagePrompt(
            postContent: 'Test', platform: 'linkedin', pillar: 'thought leadership',
            brandName: 'Studio Legale', brandSector: 'avvocato civilista',
        );
        expect($prompt)
            ->toContain('sober and dignified')
            ->toContain('no client faces');
    });

    it('buildImagePrompt include negative prompts mandatori', function () {
        $prompt = $this->builder->buildImagePrompt(
            postContent: 'Test', platform: 'instagram', pillar: 'product',
            brandName: 'Test', brandSector: 'tech',
        );
        expect($prompt)
            ->toContain('no text')
            ->toContain('no letters or numbers')
            ->toContain('no logos or watermarks');
    });

    it('buildImagePrompt include mood hint coerente con il pillar', function () {
        $prompt = $this->builder->buildImagePrompt(
            postContent: 'Test', platform: 'instagram', pillar: 'behind the scenes',
            brandName: 'Test', brandSector: 'creative',
        );
        expect($prompt)->toContain('candid, natural, atmospheric');
    });

    it('buildImagePrompt e retrocompatibile (contentType default = post)', function () {
        $prompt = $this->builder->buildImagePrompt(
            postContent: 'Test', platform: 'linkedin', pillar: 'thought leadership',
            brandName: 'Test', brandSector: 'consulenza',
        );
        expect($prompt)
            ->not->toContain('vertical 9:16')
            ->toContain('horizontal 1.91:1 composition');
    });

    it('buildStrategyPrompt contiene tutte le sezioni richieste', function () {
        $prompt = $this->builder->buildStrategyPrompt(
            brandName: 'Acme SRL',
            brandInfo: ['sector' => 'manifatturiero', 'description' => 'X', 'tone_of_voice' => 'tecnico', 'brand_values' => []],
            projectInfo: ['brief' => 'b', 'objectives' => ['thought_leadership']],
            startDate: Carbon::parse('2026-03-01'),
            endDate: Carbon::parse('2026-03-30'),
            platforms: ['linkedin', 'instagram'],
            postsPerWeek: ['linkedin' => 3, 'instagram' => 2],
            themes: ['lean'],
            urlContext: null,
            ragContext: '',
            buyerPersonas: ['personas' => [], 'scheduling_strategy' => []],
            contentMixData: [],
        );

        expect($prompt)
            ->toContain('## BRAND')
            ->toContain('## PROGETTO')
            ->toContain('## IL TUO COMPITO')
            ->toContain('STRATEGIA')
            ->toContain('editorial_narrative')
            ->toContain('pillar_distribution')
            ->toContain('"angle"')
            ->toContain('Acme SRL');
    });

    it('buildCopyPromptParts ritorna array con 3 chiavi corrette', function () {
        $parts = $this->builder->buildCopyPromptParts(
            brandName: 'Acme SRL',
            brandInfo: ['sector' => 'tech', 'description' => 'X', 'tone_of_voice' => 'pro', 'brand_values' => []],
            ragContext: '',
            strategyPlan: ['editorial_narrative' => 'N', 'posts' => [['scheduled_date' => '2026-03-01', 'angle' => 'A']]],
            batchPosts: [['scheduled_date' => '2026-03-01', 'angle' => 'A']],
            batchNum: 1,
            totalBatches: 3,
        );

        expect($parts)
            ->toBeArray()
            ->toHaveKeys(['static_brand', 'static_strategy', 'dynamic']);
    });

    it('buildCopyPromptParts include strategy plan in static_strategy', function () {
        $strategyPlan = ['editorial_narrative' => 'Test narrative xyzabc', 'posts' => []];
        $parts = $this->builder->buildCopyPromptParts(
            brandName: 'Test', brandInfo: ['sector' => 'tech'], ragContext: '',
            strategyPlan: $strategyPlan, batchPosts: [], batchNum: 1, totalBatches: 1,
        );

        expect($parts['static_strategy'])
            ->toContain('STRATEGY PLAN')
            ->toContain('Test narrative xyzabc');
    });

    it('buildCopyPromptParts dynamic mention batch number', function () {
        $parts = $this->builder->buildCopyPromptParts(
            brandName: 'Test', brandInfo: ['sector' => 'tech'], ragContext: '',
            strategyPlan: ['posts' => []], batchPosts: [['scheduled_date' => '2026-03-01']],
            batchNum: 2, totalBatches: 5,
        );

        expect($parts['dynamic'])
            ->toContain('BATCH 2/5')
            ->toContain('JSON array');
    });
});
