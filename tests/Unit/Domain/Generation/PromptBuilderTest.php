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
});
