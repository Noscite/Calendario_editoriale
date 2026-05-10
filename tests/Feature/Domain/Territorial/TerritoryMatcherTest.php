<?php

declare(strict_types=1);

use App\Domain\Territorial\Models\Municipality;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Services\TerritoryMatcher;

beforeEach(function () {
    Municipality::create([
        'codice_istat' => '015146', 'nome' => 'Milano', 'nome_normalized' => 'milano',
        'provincia' => 'MI', 'regione' => 'Lombardia',
    ]);
    Municipality::create([
        'codice_istat' => '058091', 'nome' => 'Roma', 'nome_normalized' => 'roma',
        'provincia' => 'RM', 'regione' => 'Lazio',
    ]);
    Municipality::create([
        'codice_istat' => '015230', 'nome' => "Vaprio d'Adda", 'nome_normalized' => "vaprio d'adda",
        'provincia' => 'MI', 'regione' => 'Lombardia',
    ]);
});

it('returns empty collection when brand has no vertical', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org); // niente vertical

    TerritorialEvent::create([
        'source' => 'e015', 'external_id' => 'e1', 'title' => 'Sagra',
        'city' => 'Milano', 'province' => 'MI',
        'start_at' => now()->addDays(10),
        'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    $events = app(TerritoryMatcher::class)->eligibleEvents($brand, now(), now()->addDays(30));
    expect($events)->toHaveCount(0);
});

it('filters events by region for unpli_regional vertical', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org, [
        'vertical' => 'unpli_regional',
        'territory_meta' => ['region' => 'Lombardia'],
    ]);

    TerritorialEvent::create([
        'source' => 'e015', 'external_id' => 'e_mi', 'title' => 'Evento Milano',
        'city' => 'Milano', 'province' => 'MI',
        'start_at' => now()->addDays(5),
        'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);
    TerritorialEvent::create([
        'source' => 'e015', 'external_id' => 'e_rm', 'title' => 'Evento Roma',
        'city' => 'Roma', 'province' => 'RM',
        'start_at' => now()->addDays(5),
        'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    $events = app(TerritoryMatcher::class)->eligibleEvents($brand, now(), now()->addDays(30));

    expect($events)->toHaveCount(1);
    expect($events->first()->external_id)->toBe('e_mi');
});

it('filters events by single municipality for pro_loco vertical', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org, [
        'vertical' => 'pro_loco',
        'territory_meta' => ['municipality_istat' => '015230'], // Vaprio d'Adda
    ]);

    TerritorialEvent::create([
        'source' => 'e015', 'external_id' => 'e_vap', 'title' => 'Concerto Vaprio',
        'city' => "Vaprio d'Adda", 'province' => 'MI',
        'start_at' => now()->addDays(5),
        'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);
    TerritorialEvent::create([
        'source' => 'e015', 'external_id' => 'e_mi', 'title' => 'Sagra Milano',
        'city' => 'Milano', 'province' => 'MI',
        'start_at' => now()->addDays(5),
        'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    $events = app(TerritoryMatcher::class)->eligibleEvents($brand, now(), now()->addDays(30));

    expect($events)->toHaveCount(1);
    expect($events->first()->external_id)->toBe('e_vap');
});

it('returns empty when vertical is set but territory_meta is missing', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org, [
        'vertical' => 'unpli_regional',
        'territory_meta' => null, // missing
    ]);

    TerritorialEvent::create([
        'source' => 'e015', 'external_id' => 'e1', 'title' => 'Test',
        'city' => 'Milano', 'province' => 'MI',
        'start_at' => now()->addDays(5),
        'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    $events = app(TerritoryMatcher::class)->eligibleEvents($brand, now(), now()->addDays(30));
    expect($events)->toHaveCount(0);
});
