<?php

declare(strict_types=1);

use App\Domain\Generation\Services\PromptBuilder;
use Carbon\Carbon;

beforeEach(function () {
    $this->builder = app(PromptBuilder::class);
});

function buildStrategyPromptFor(PromptBuilder $builder, string $brandName, string $sector): string
{
    return $builder->buildStrategyPrompt(
        brandName:      $brandName,
        brandInfo:      [
            'sector'           => $sector,
            'description'      => 'Test description',
            'tone_of_voice'    => 'professionale',
            'brand_values'     => ['etica', 'preparazione'],
            'voice_examples'   => [],
            'narrative_assets' => [],
        ],
        projectInfo:    [
            'brief'      => 'Test brief',
            'objectives' => [],
        ],
        startDate:      Carbon::parse('2026-06-01'),
        endDate:        Carbon::parse('2026-06-30'),
        platforms:      ['linkedin'],
        postsPerWeek:   ['linkedin' => 2],
        themes:         ['Educational'],
        urlContext:     null,
        ragContext:     '',
        buyerPersonas:  [],
        contentMixData: [],
    );
}

it('injects deontological section in system prompt for psicologia brand', function () {
    $prompt = buildStrategyPromptFor($this->builder, 'Dr.ssa Test Psicologa', 'psicologia');

    expect($prompt)->toContain('VINCOLI DEONTOLOGICI');
    expect($prompt)->toContain('Psicologia');
    expect($prompt)->toContain('Ordine Psicologi');
    expect($prompt)->toContain('guarigione garantita');
    expect($prompt)->toContain('primo colloquio conoscitivo');
});

it('injects deontological section with disclaimers for finanza_indipendente brand', function () {
    $prompt = buildStrategyPromptFor($this->builder, 'Test Advisor Indipendente', 'finanza_indipendente');

    expect($prompt)->toContain('VINCOLI DEONTOLOGICI');
    expect($prompt)->toContain('Consulenza Finanziaria Indipendente');
    expect($prompt)->toContain('Consob');
    expect($prompt)->toContain('rendimenti passati');
    expect($prompt)->toContain('MiFID II');
});

it('does NOT inject deontological section for non-regulated brand', function () {
    $prompt = buildStrategyPromptFor($this->builder, 'Pro Loco Test', 'turismo');

    expect($prompt)->not->toContain('VINCOLI DEONTOLOGICI');
});

it('keeps existing VINCOLI ANTI-INVENZIONE section alongside deontological', function () {
    $prompt = buildStrategyPromptFor($this->builder, 'Dr.ssa Test Psicologa', 'psicologia');

    expect($prompt)->toContain('VINCOLI ANTI-INVENZIONE');
    expect($prompt)->toContain('VINCOLI DEONTOLOGICI');
});

it('user prompt copy parts include deontological reminder for regulated brand', function () {
    $parts = $this->builder->buildCopyPromptParts(
        brandName:     'Dr.ssa Test',
        brandInfo:     ['sector' => 'psicologia', 'voice_examples' => []],
        ragContext:    '',
        strategyPlan:  ['posts' => []],
        batchPosts:    [],
        batchNum:      1,
        totalBatches:  1,
    );

    $dynamic = $parts['dynamic'] ?? '';
    expect($dynamic)->toContain('PROMEMORIA DEONTOLOGICO');
    expect($dynamic)->toContain('Psicologia');
});

it('user prompt copy parts omit deontological reminder for non-regulated brand', function () {
    $parts = $this->builder->buildCopyPromptParts(
        brandName:     'Pro Loco Test',
        brandInfo:     ['sector' => 'turismo', 'voice_examples' => []],
        ragContext:    '',
        strategyPlan:  ['posts' => []],
        batchPosts:    [],
        batchNum:      1,
        totalBatches:  1,
    );

    $dynamic = $parts['dynamic'] ?? '';
    expect($dynamic)->not->toContain('PROMEMORIA DEONTOLOGICO');
});
