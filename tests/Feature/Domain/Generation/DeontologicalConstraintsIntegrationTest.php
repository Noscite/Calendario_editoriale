<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Services\PromptBuilder;
use Carbon\Carbon;

beforeEach(function () {
    [$user, $org] = createAuthenticatedUser();

    $this->brandPsico = createBrand($org, [
        'name'   => 'Dr.ssa Test Psicologa',
        'sector' => 'psicologia, formazione',
    ]);
    $this->brandPsico->syncDeontologicalConstraints(['psicologia']);
    $this->brandPsico->refresh();

    $this->brandFinanza = createBrand($org, [
        'name'   => 'Test Advisor Indipendente',
        'sector' => 'consulenza finanziaria indipendente',
    ]);
    $this->brandFinanza->syncDeontologicalConstraints(['finanza_indipendente']);
    $this->brandFinanza->refresh();

    $this->brandTurismo = createBrand($org, [
        'name'     => 'Pro Loco Test',
        'sector'   => 'turismo',
        'vertical' => 'pro_loco',
    ]);

    $this->brandMulti = createBrand($org, [
        'name'   => 'Studio Multi-disciplinare',
        'sector' => 'psicologia, finanza',
    ]);
    $this->brandMulti->syncDeontologicalConstraints(['psicologia', 'finanza_indipendente']);
    $this->brandMulti->refresh();

    $this->builder = app(PromptBuilder::class);
});

function buildStrategyPromptForBrand(PromptBuilder $builder, ?Brand $brand, string $brandName = 'Test'): string
{
    return $builder->buildStrategyPrompt(
        brandName:      $brandName,
        brandInfo:      [
            'sector'           => $brand?->sector ?? 'tech',
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
        brand:          $brand,
    );
}

it('injects deontological section in system prompt for psicologia brand', function () {
    $prompt = buildStrategyPromptForBrand($this->builder, $this->brandPsico, 'Dr.ssa Test Psicologa');

    expect($prompt)->toContain('VINCOLI DEONTOLOGICI');
    expect($prompt)->toContain('Psicologia');
    expect($prompt)->toContain('Ordine Psicologi');
    expect($prompt)->toContain('guarigione garantita');
    expect($prompt)->toContain('primo colloquio conoscitivo');
});

it('injects deontological section with disclaimers for finanza_indipendente brand', function () {
    $prompt = buildStrategyPromptForBrand($this->builder, $this->brandFinanza, 'Test Advisor Indipendente');

    expect($prompt)->toContain('VINCOLI DEONTOLOGICI');
    expect($prompt)->toContain('Consulenza Finanziaria Indipendente');
    expect($prompt)->toContain('Consob');
    expect($prompt)->toContain('rendimenti passati');
    expect($prompt)->toContain('MiFID II');
});

it('injects merged deontological section for multi-constraint brand', function () {
    $prompt = buildStrategyPromptForBrand($this->builder, $this->brandMulti, 'Studio Multi-disciplinare');

    expect($prompt)->toContain('VINCOLI DEONTOLOGICI');
    expect($prompt)->toContain('Psicologia / Psicoterapia / Counseling');
    expect($prompt)->toContain('Consulenza Finanziaria Indipendente');
    expect($prompt)->toContain('guarigione garantita');
    expect($prompt)->toContain('rendimento garantito');
});

it('does NOT inject deontological section for non-regulated brand', function () {
    $prompt = buildStrategyPromptForBrand($this->builder, $this->brandTurismo, 'Pro Loco Test');

    expect($prompt)->not->toContain('VINCOLI DEONTOLOGICI');
});

it('does NOT inject deontological section when brand parameter is null', function () {
    $prompt = buildStrategyPromptForBrand($this->builder, null, 'No-brand call');

    expect($prompt)->not->toContain('VINCOLI DEONTOLOGICI');
});

it('keeps existing VINCOLI ANTI-INVENZIONE section alongside deontological', function () {
    $prompt = buildStrategyPromptForBrand($this->builder, $this->brandPsico, 'Dr.ssa Test Psicologa');

    expect($prompt)->toContain('VINCOLI ANTI-INVENZIONE');
    expect($prompt)->toContain('VINCOLI DEONTOLOGICI');
});

it('user prompt copy parts include deontological reminder for regulated brand', function () {
    $parts = $this->builder->buildCopyPromptParts(
        brandName:     'Dr.ssa Test',
        brandInfo:     ['sector' => 'psicologia, formazione', 'voice_examples' => []],
        ragContext:    '',
        strategyPlan:  ['posts' => []],
        batchPosts:    [],
        batchNum:      1,
        totalBatches:  1,
        brand:         $this->brandPsico,
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
        brand:         $this->brandTurismo,
    );

    $dynamic = $parts['dynamic'] ?? '';
    expect($dynamic)->not->toContain('PROMEMORIA DEONTOLOGICO');
});

it('user prompt copy parts omit deontological reminder when brand is null', function () {
    $parts = $this->builder->buildCopyPromptParts(
        brandName:     'Legacy call',
        brandInfo:     ['sector' => 'psicologia', 'voice_examples' => []],
        ragContext:    '',
        strategyPlan:  ['posts' => []],
        batchPosts:    [],
        batchNum:      1,
        totalBatches:  1,
    );

    $dynamic = $parts['dynamic'] ?? '';
    expect($dynamic)->not->toContain('PROMEMORIA DEONTOLOGICO');
});
