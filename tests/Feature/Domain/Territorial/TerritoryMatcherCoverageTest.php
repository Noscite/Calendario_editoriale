<?php

declare(strict_types=1);

use App\Domain\Territorial\Models\Municipality;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Services\TerritoryMatcher;
use Carbon\Carbon;

beforeEach(function () {
    // Seed minimo municipalities Lombardia per coprire i casi rilevanti
    $rows = [
        ['015146', 'Milano',                  'milano',                  'MI'],
        ['015230', "Vaprio d'Adda",           "vaprio d'adda",           'MI'],
        ['015208', 'Sesto San Giovanni',      'sesto san giovanni',      'MI'],
        ['015070', 'Cernusco sul Naviglio',   'cernusco sul naviglio',   'MI'],
        ['015041', 'Cambiago',                'cambiago',                'MI'],
        ['015112', 'Inzago',                  'inzago',                  'MI'],
        ['015137', 'Masate',                  'masate',                  'MI'],
        ['108009', 'Caponago',                'caponago',                'MB'],
        ['108033', 'Monza',                   'monza',                   'MB'],
        ['016024', 'Bergamo',                 'bergamo',                 'BG'],
    ];
    foreach ($rows as [$istat, $nome, $norm, $prov]) {
        Municipality::create([
            'codice_istat'    => $istat,
            'nome'            => $nome,
            'nome_normalized' => $norm,
            'provincia'       => $prov,
            'regione'         => 'Lombardia',
        ]);
    }
    // Comune fuori Lombardia per esclusione
    Municipality::create([
        'codice_istat' => '058091', 'nome' => 'Roma', 'nome_normalized' => 'roma',
        'provincia' => 'RM', 'regione' => 'Lazio',
    ]);

    $this->periodStart = Carbon::parse('2026-05-07');
    $this->periodEnd   = Carbon::parse('2026-05-30');

    [, $org] = createAuthenticatedUser();
    $this->brand = createBrand($org, [
        'vertical'       => 'unpli_regional',
        'territory_meta' => ['region' => 'Lombardia'],
    ]);

    $this->matcher = app(TerritoryMatcher::class);
});

function makeMatcherEvent(array $overrides = []): TerritorialEvent
{
    return TerritorialEvent::create(array_merge([
        'source'        => 'e015',
        'external_id'   => 'test-' . uniqid(),
        'title'         => 'Test Event',
        'status'        => 'active',
        'start_at'      => '2026-05-15 21:00:00',
        'end_at'        => '2026-05-15 23:00:00',
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ], $overrides));
}

it('matches province sigle case-insensitive (MI, Mi, mi)', function () {
    makeMatcherEvent(['title' => 'EV-MI', 'province' => 'MI', 'city' => 'Milano']);
    makeMatcherEvent(['title' => 'EV-Mi', 'province' => 'Mi', 'city' => "Vaprio d'Adda"]);
    makeMatcherEvent(['title' => 'EV-mi', 'province' => 'mi', 'city' => 'Sesto San Giovanni']);

    $matched = $this->matcher->eligibleEvents($this->brand, $this->periodStart, $this->periodEnd);

    $titles = $matched->pluck('title')->all();
    expect($titles)->toContain('EV-MI');
    expect($titles)->toContain('EV-Mi');
    expect($titles)->toContain('EV-mi');
});

it('matches extended province names case-insensitive (Milano, MILANO, milano)', function () {
    makeMatcherEvent(['title' => 'EV-Milano', 'province' => 'Milano', 'city' => 'Cernusco sul Naviglio']);
    makeMatcherEvent(['title' => 'EV-MILANO', 'province' => 'MILANO', 'city' => 'Cambiago']);
    makeMatcherEvent(['title' => 'EV-milano', 'province' => 'milano', 'city' => 'Inzago']);

    $matched = $this->matcher->eligibleEvents($this->brand, $this->periodStart, $this->periodEnd);

    $titles = $matched->pluck('title')->all();
    expect($titles)->toContain('EV-Milano');
    expect($titles)->toContain('EV-MILANO');
    expect($titles)->toContain('EV-milano');
});

it('matches non-standard province aliases (Monza Brianza variants)', function () {
    makeMatcherEvent(['title' => 'EV-MB-1', 'province' => 'Monza Brianza',         'city' => 'Caponago']);
    makeMatcherEvent(['title' => 'EV-MB-2', 'province' => 'Monza e della Brianza', 'city' => 'Caponago']);
    makeMatcherEvent(['title' => 'EV-MB-3', 'province' => 'MONZA BRIANZA',         'city' => 'Caponago']);

    $matched = $this->matcher->eligibleEvents($this->brand, $this->periodStart, $this->periodEnd);

    $titles = $matched->pluck('title')->all();
    expect($titles)->toContain('EV-MB-1');
    expect($titles)->toContain('EV-MB-2');
    expect($titles)->toContain('EV-MB-3');
});

it('matches via city fallback when province is dirty (Italia / null)', function () {
    makeMatcherEvent([
        'title'    => 'EV-Italia',
        'province' => 'Italia',
        'city'     => 'Cernusco sul Naviglio',
    ]);
    makeMatcherEvent([
        'title'    => 'EV-NullProv',
        'province' => null,
        'city'     => 'Bergamo',
    ]);

    $matched = $this->matcher->eligibleEvents($this->brand, $this->periodStart, $this->periodEnd);

    $titles = $matched->pluck('title')->all();
    expect($titles)->toContain('EV-Italia');
    expect($titles)->toContain('EV-NullProv');
});

it('excludes events outside the region (province + city both non-Lombardia)', function () {
    makeMatcherEvent(['title' => 'EV-Roma',   'province' => 'RM',     'city' => 'Roma']);
    makeMatcherEvent(['title' => 'EV-Napoli', 'province' => 'Napoli', 'city' => 'Napoli']);

    $matched = $this->matcher->eligibleEvents($this->brand, $this->periodStart, $this->periodEnd);

    $titles = $matched->pluck('title')->all();
    expect($titles)->not->toContain('EV-Roma');
    expect($titles)->not->toContain('EV-Napoli');
});

it('matches multi-month ongoing events (overlap window, not whereBetween start_at)', function () {
    makeMatcherEvent([
        'title'    => 'Appassionatamente 2026',
        'province' => 'Milano',
        'city'     => 'Inzago',
        'start_at' => '2026-03-14 15:00:00',
        'end_at'   => '2026-09-19 15:00:00',
    ]);
    makeMatcherEvent([
        'title'    => 'Maggio Masatese',
        'province' => 'MI',
        'city'     => 'Masate',
        'start_at' => '2026-05-02 10:00:00',
        'end_at'   => '2026-06-06 22:00:00',
    ]);
    makeMatcherEvent([
        'title'    => 'Silent Disco',
        'province' => 'Milano',
        'city'     => 'Masate',
        'start_at' => '2026-05-15 21:00:00',
        'end_at'   => '2026-05-15 23:59:00',
    ]);

    $matched = $this->matcher->eligibleEvents($this->brand, $this->periodStart, $this->periodEnd);

    $titles = $matched->pluck('title')->all();
    expect($titles)->toContain('Appassionatamente 2026');
    expect($titles)->toContain('Maggio Masatese');
    expect($titles)->toContain('Silent Disco');
});

it('excludes events that ended before the period or start after the period', function () {
    makeMatcherEvent([
        'title'    => 'Concerto Aprile',
        'province' => 'MI',
        'city'     => 'Milano',
        'start_at' => '2026-04-01 21:00:00',
        'end_at'   => '2026-04-30 23:00:00',
    ]);
    makeMatcherEvent([
        'title'    => 'Concerto Luglio',
        'province' => 'MI',
        'city'     => 'Milano',
        'start_at' => '2026-07-15 21:00:00',
        'end_at'   => '2026-07-15 23:00:00',
    ]);

    $matched = $this->matcher->eligibleEvents($this->brand, $this->periodStart, $this->periodEnd);

    $titles = $matched->pluck('title')->all();
    expect($titles)->not->toContain('Concerto Aprile');
    expect($titles)->not->toContain('Concerto Luglio');
});

it('matches event with null end_at if start_at is within or before period', function () {
    makeMatcherEvent([
        'title'    => 'EV-NoEnd',
        'province' => 'MI',
        'city'     => 'Milano',
        'start_at' => '2026-05-15 21:00:00',
        'end_at'   => null,
    ]);

    $matched = $this->matcher->eligibleEvents($this->brand, $this->periodStart, $this->periodEnd);

    expect($matched->pluck('title')->all())->toContain('EV-NoEnd');
});
