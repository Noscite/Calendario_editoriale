<?php

declare(strict_types=1);

use App\Domain\Brand\Enums\Sector;

beforeEach(function () {
    [$user, $org] = createAuthenticatedUser();
    $this->brand = createBrand($org, ['sector' => 'psicologia, formazione']);
});

it('starts with no deontological constraints', function () {
    expect($this->brand->hasDeontologicalConstraints())->toBeFalse();
    expect($this->brand->deontologicalConstraintSlugs()->toArray())->toBe([]);
    expect($this->brand->deontologicalConstraintSectors()->toArray())->toBe([]);
});

it('syncs single constraint', function () {
    $this->brand->syncDeontologicalConstraints(['psicologia']);
    $this->brand->refresh();

    expect($this->brand->hasDeontologicalConstraints())->toBeTrue();
    expect($this->brand->deontologicalConstraintSlugs()->toArray())->toBe(['psicologia']);
    expect($this->brand->deontologicalConstraintSectors()->first())->toBe(Sector::Psicologia);
});

it('syncs multiple constraints', function () {
    $this->brand->syncDeontologicalConstraints(['psicologia', 'finanza_indipendente']);
    $this->brand->refresh();

    $slugs = $this->brand->deontologicalConstraintSlugs()->toArray();
    expect($slugs)->toContain('psicologia');
    expect($slugs)->toContain('finanza_indipendente');
    expect(count($slugs))->toBe(2);
});

it('replaces existing constraints on sync (replace strategy)', function () {
    $this->brand->syncDeontologicalConstraints(['psicologia']);
    $this->brand->syncDeontologicalConstraints(['finanza_indipendente', 'legale']);
    $this->brand->refresh();

    $slugs = $this->brand->deontologicalConstraintSlugs()->toArray();
    expect($slugs)->not->toContain('psicologia');
    expect($slugs)->toContain('finanza_indipendente');
    expect($slugs)->toContain('legale');
});

it('filters out invalid slugs defensively', function () {
    $this->brand->syncDeontologicalConstraints(['psicologia', 'turismo', 'invalid_slug', 'tech']);
    $this->brand->refresh();

    expect($this->brand->deontologicalConstraintSlugs()->toArray())->toBe(['psicologia']);
});

it('dedups slugs on sync', function () {
    $this->brand->syncDeontologicalConstraints(['psicologia', 'psicologia', 'legale']);
    $this->brand->refresh();

    expect($this->brand->deontologicalConstraints()->count())->toBe(2);
});

it('clears constraints when synced with empty array', function () {
    $this->brand->syncDeontologicalConstraints(['psicologia']);
    $this->brand->syncDeontologicalConstraints([]);
    $this->brand->refresh();

    expect($this->brand->hasDeontologicalConstraints())->toBeFalse();
});

it('Sector::regulatedValues returns 5 expected slugs', function () {
    $values = Sector::regulatedValues();

    expect($values)->toContain('psicologia');
    expect($values)->toContain('salute');
    expect($values)->toContain('legale');
    expect($values)->toContain('finanza');
    expect($values)->toContain('finanza_indipendente');
    expect(count($values))->toBe(5);
});

it('Sector::regulatedOptions returns labeled options', function () {
    $options = Sector::regulatedOptions();

    expect($options)->toBeArray();
    expect(count($options))->toBe(5);
    expect($options[0])->toHaveKeys(['value', 'label']);

    $psicologia = collect($options)->firstWhere('value', 'psicologia');
    expect($psicologia['label'])->toBe('Psicologia / Psicoterapia / Counseling');
});
