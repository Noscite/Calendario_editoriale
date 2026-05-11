<?php

declare(strict_types=1);

use App\Domain\Brand\Enums\Sector;
use App\Domain\Brand\Services\SocialDeontologicalConstraints;

beforeEach(function () {
    $this->service = app(SocialDeontologicalConstraints::class);
});

it('returns constraints for psicologia', function () {
    $c = $this->service->getFor(Sector::Psicologia);

    expect($c)->not->toBeNull();
    expect($c['forbidden_phrases'])->toContain('guarigione garantita');
    expect($c['forbidden_phrases'])->toContain('prima seduta gratis');
    expect($c['forbidden_themes'])->toContain('Testimonianze di pazienti');
    expect($c['cta_guidelines'])->toContain('primo colloquio conoscitivo');
    expect($c['legal_basis'])->toContain('Ordine Psicologi');
});

it('returns constraints for finanza_indipendente with required disclaimers', function () {
    $c = $this->service->getFor(Sector::FinanzaIndipendente);

    expect($c)->not->toBeNull();
    expect($c['forbidden_phrases'])->toContain('rendimento garantito');
    expect($c['forbidden_phrases'])->toContain('investimento sicuro');
    expect($c['required_disclaimers'])->not->toBeEmpty();
    expect($c['required_disclaimers'][0])->toContain('rendimenti passati');
    expect($c['legal_basis'])->toContain('Consob');
    expect($c['legal_basis'])->toContain('MiFID II');
});

it('returns constraints for salute/legale/finanza but distinct from psicologia/finanza_indipendente', function () {
    $salute       = $this->service->getFor(Sector::Salute);
    $psicologia   = $this->service->getFor(Sector::Psicologia);
    $finanza      = $this->service->getFor(Sector::Finanza);
    $finanzaIndep = $this->service->getFor(Sector::FinanzaIndipendente);

    expect($salute['preferred_themes'])->not->toBe($psicologia['preferred_themes']);
    expect($finanzaIndep['preferred_themes'])->toContain('Iscrizione Albo OCF, certificazioni (CFP, CFA, EFP)');
    expect($finanza['legal_basis'])->toContain('Banca d\'Italia');
});

it('returns null for non-regulated sectors', function () {
    expect($this->service->getFor(Sector::Turismo))->toBeNull();
    expect($this->service->getFor(Sector::Food))->toBeNull();
    expect($this->service->getFor(Sector::Tech))->toBeNull();
});

it('Sector::isRegulated() identifies regulated sectors correctly', function () {
    expect(Sector::Psicologia->isRegulated())->toBeTrue();
    expect(Sector::FinanzaIndipendente->isRegulated())->toBeTrue();
    expect(Sector::Salute->isRegulated())->toBeTrue();
    expect(Sector::Legale->isRegulated())->toBeTrue();
    expect(Sector::Finanza->isRegulated())->toBeTrue();
    expect(Sector::Turismo->isRegulated())->toBeFalse();
    expect(Sector::Food->isRegulated())->toBeFalse();
});

// ─── getForBrand: merge multi-vincolo ──────────────────────────

it('getForBrand returns null for brand with no constraints', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['sector' => 'psicologia, formazione']);

    expect($this->service->getForBrand($brand))->toBeNull();
});

it('getForBrand returns single constraint set with sector_labels for one-constraint brand', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['sector' => 'psicologia']);
    $brand->syncDeontologicalConstraints(['psicologia']);

    $result = $this->service->getForBrand($brand);

    expect($result)->not->toBeNull();
    expect($result['forbidden_phrases'])->toContain('guarigione garantita');
    expect($result['sector_labels'])->toBe(['Psicologia / Psicoterapia / Counseling']);
    expect($result['legal_basis'])->toContain('Ordine Psicologi');
});

it('getForBrand merges constraints for multi-constraint brand', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['sector' => 'psicologia, finanza']);
    $brand->syncDeontologicalConstraints(['psicologia', 'finanza_indipendente']);

    $result = $this->service->getForBrand($brand);

    expect($result)->not->toBeNull();

    expect($result['forbidden_phrases'])->toContain('guarigione garantita');
    expect($result['forbidden_phrases'])->toContain('rendimento garantito');

    expect($result['required_disclaimers'])->not->toBeEmpty();
    $disclaimersBlob = implode(' ', $result['required_disclaimers']);
    expect($disclaimersBlob)->toContain('rendimenti passati');

    expect($result['sector_labels'])->toContain('Psicologia / Psicoterapia / Counseling');
    expect($result['sector_labels'])->toContain('Consulenza Finanziaria Indipendente (OCF)');

    expect($result['legal_basis'])->toContain('Ordine Psicologi');
    expect($result['legal_basis'])->toContain('Consob');

    expect($result['tone_guidance'])->toContain('[Psicologia');
    expect($result['tone_guidance'])->toContain('[Consulenza Finanziaria Indipendente');
});

it('getForBrand dedups identical phrases across constraints', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['sector' => 'salute, psicologia']);
    $brand->syncDeontologicalConstraints(['psicologia', 'salute']);

    $result = $this->service->getForBrand($brand);

    $occurrences = collect($result['forbidden_phrases'])
        ->filter(fn ($p) => $p === 'guarigione garantita')
        ->count();

    expect($occurrences)->toBe(1);
});
