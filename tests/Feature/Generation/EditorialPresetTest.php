<?php

declare(strict_types=1);

use App\Domain\Generation\Presets\EditorialPreset;
use App\Domain\Generation\Services\PromptBuilder;
use App\Domain\Post\Enums\PostType;
use Carbon\Carbon;

/**
 * Verifica il preset editoriale B2B Authority:
 *  - inietta la struttura settimanale (giorno → tipo post) nel prompt batch
 *  - il preset Standard (o assente) non altera il prompt esistente
 *  - weeklySchedule() ritorna array vuoto per Standard
 */
describe('EditorialPreset', function () {

    beforeEach(function () {
        $this->builder = new PromptBuilder();
        $this->startDate = Carbon::parse('2026-03-01');
        $this->endDate   = Carbon::parse('2026-03-31');
    });

    $buildPrompt = function (PromptBuilder $builder, array $projectInfo, Carbon $start, Carbon $end): string {
        return $builder->buildBatchPrompt(
            brandName:     'Acme SRL',
            brandInfo:     ['sector' => 'Tech', 'description' => 'Software', 'tone_of_voice' => 'professionale', 'brand_values' => []],
            projectInfo:   $projectInfo,
            startDate:     $start,
            endDate:       $end,
            platforms:     ['linkedin', 'instagram'],
            postsPerWeek:  ['linkedin' => 3, 'instagram' => 4],
            themes:        ['tema'],
            urlContext:    null,
            ragContext:    '',
            styleGuide:    '',
            buyerPersonas: ['personas' => [], 'scheduling_strategy' => []],
            contentMixData: [],
        );
    };

    it('il preset B2B produce un prompt che contiene i 5 tipi previsti', function () use ($buildPrompt) {
        $prompt = $buildPrompt(
            $this->builder,
            ['brief' => 'Test brief', 'objectives' => ['brand_awareness'], 'editorial_preset' => EditorialPreset::B2BAuthority],
            $this->startDate,
            $this->endDate,
        );

        expect($prompt)
            ->toContain('STRUTTURA SETTIMANALE')
            ->toContain(PostType::Engagement->label())
            ->toContain(PostType::Educational->label())
            ->toContain(PostType::LeadMagnet->label())
            ->toContain(PostType::SocialProof->label())
            ->toContain(PostType::BehindTheScenes->label());
    });

    it('accetta il preset B2B anche come stringa (value dal cast Eloquent)', function () use ($buildPrompt) {
        $prompt = $buildPrompt(
            $this->builder,
            ['editorial_preset' => 'b2b_authority'],
            $this->startDate,
            $this->endDate,
        );

        expect($prompt)->toContain('STRUTTURA SETTIMANALE');
    });

    it('preset Standard non altera il prompt esistente', function () use ($buildPrompt) {
        $baseline = $buildPrompt(
            $this->builder,
            ['brief' => 'Test brief', 'objectives' => ['brand_awareness']],
            $this->startDate,
            $this->endDate,
        );

        $withStandard = $buildPrompt(
            $this->builder,
            ['brief' => 'Test brief', 'objectives' => ['brand_awareness'], 'editorial_preset' => EditorialPreset::Standard],
            $this->startDate,
            $this->endDate,
        );

        expect($withStandard)
            ->toBe($baseline)
            ->not->toContain('STRUTTURA SETTIMANALE');
    });

    it('weeklySchedule() ritorna array vuoto per Standard', function () {
        expect(EditorialPreset::Standard->weeklySchedule())->toBe([]);
    });

    it('weeklySchedule() B2B mappa i 5 giorni lavorativi ai tipi previsti', function () {
        $schedule = EditorialPreset::B2BAuthority->weeklySchedule();

        expect($schedule)
            ->toHaveCount(5)
            ->and($schedule['lunedì'])->toBe(PostType::Engagement)
            ->and($schedule['martedì'])->toBe(PostType::Educational)
            ->and($schedule['mercoledì'])->toBe(PostType::LeadMagnet)
            ->and($schedule['giovedì'])->toBe(PostType::SocialProof)
            ->and($schedule['venerdì'])->toBe(PostType::BehindTheScenes);
    });

    it('options() include Standard e B2B Authority', function () {
        $options = EditorialPreset::options();

        expect($options)
            ->toHaveKey('standard')
            ->toHaveKey('b2b_authority');
    });
});
