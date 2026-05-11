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
