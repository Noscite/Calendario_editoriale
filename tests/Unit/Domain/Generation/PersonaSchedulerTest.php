<?php

declare(strict_types=1);

use App\Domain\Generation\Presets\EditorialPreset;
use App\Domain\Generation\Services\PersonaScheduler;
use Carbon\Carbon;

/**
 * Regressione: PersonaScheduler::redistributePostsWithPersonas() scartava
 * silenziosamente i post eccedenti il numero di slot definiti, invece di
 * rispettare $ppw (posts_per_week). Vedi bug prod progetto 845 (5 richiesti → 2 salvati).
 *
 * Il numero di post salvati per piattaforma per settimana deve essere
 * min($ppw, post rimanenti) — mai limitato dal numero di slot.
 */
describe('PersonaScheduler::redistributePostsWithPersonas', function () {

    beforeEach(function () {
        $this->scheduler = new PersonaScheduler();
        // Lunedì garantito, così gli slot feriali (0=lun … 4=ven) cadono tutti in-range.
        $this->monday = Carbon::parse('2026-03-02')->startOfWeek(Carbon::MONDAY);
    });

    // Costruisce N post per una piattaforma, ciascuno con un marcatore univoco.
    $makePosts = function (string $platform, int $n): array {
        $posts = [];
        for ($i = 0; $i < $n; $i++) {
            $posts[] = ['platform' => $platform, 'content' => "{$platform}-post-{$i}"];
        }
        return $posts;
    };

    it('regressione: 5 post linkedin, ppw=5, strategia vuota → 5 post redistribuiti (non 2)', function () use ($makePosts) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(6); // 1 settimana

        $out = $this->scheduler->redistributePostsWithPersonas(
            $makePosts('linkedin', 5),
            ['linkedin' => 5],
            $start,
            $end,
            [], // strategia vuota
        );

        expect($out)->toHaveCount(5);
    });

    it('4 post instagram, ppw=4, strategia con 3 slot → 4 post redistribuiti', function () use ($makePosts) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(6);

        $personas = ['scheduling_strategy' => ['instagram' => ['optimal_slots' => [
            ['day' => 0, 'time' => '18:00', 'priority' => 1],
            ['day' => 2, 'time' => '18:00', 'priority' => 2],
            ['day' => 4, 'time' => '18:00', 'priority' => 3],
        ]]]];

        $out = $this->scheduler->redistributePostsWithPersonas(
            $makePosts('instagram', 4),
            ['instagram' => 4],
            $start,
            $end,
            $personas,
        );

        expect($out)->toHaveCount(4);
    });

    it('ppw maggiore dei post disponibili → tutti i post disponibili, nessun duplicato', function () use ($makePosts) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(6);

        $out = $this->scheduler->redistributePostsWithPersonas(
            $makePosts('linkedin', 3),
            ['linkedin' => 5], // richiesti 5, disponibili 3
            $start,
            $end,
            [],
        );

        expect($out)->toHaveCount(3);

        // Nessun duplicato (date,time) per la stessa piattaforma.
        $pairs = array_map(fn ($p) => $p['scheduled_date'] . ' ' . $p['scheduled_time'], $out);
        expect($pairs)->toBe(array_values(array_unique($pairs)));
    });

    it('slot definiti dalle personas preservati con date/orari originali', function () use ($makePosts) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(6);

        $personas = ['scheduling_strategy' => ['linkedin' => ['optimal_slots' => [
            ['day' => 1, 'time' => '09:15', 'priority' => 1], // martedì
            ['day' => 3, 'time' => '16:45', 'priority' => 2], // giovedì
        ]]]];

        $out = $this->scheduler->redistributePostsWithPersonas(
            $makePosts('linkedin', 2),
            ['linkedin' => 2],
            $start,
            $end,
            $personas,
        );

        expect($out)->toHaveCount(2);

        $times = array_map(fn ($p) => $p['scheduled_time'], $out);
        expect($times)->toContain('09:15')->toContain('16:45');

        // Date attese: martedì e giovedì della settimana di partenza.
        $dates = array_map(fn ($p) => $p['scheduled_date'], $out);
        expect($dates)->toContain($start->copy()->addDays(1)->toDateString())  // martedì
            ->toContain($start->copy()->addDays(3)->toDateString());           // giovedì
    });

    it('nessuna data fuori dall’intervallo [startDate, endDate]', function () use ($makePosts) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(6);

        $out = $this->scheduler->redistributePostsWithPersonas(
            $makePosts('linkedin', 5),
            ['linkedin' => 5],
            $start,
            $end,
            [],
        );

        foreach ($out as $p) {
            $d = Carbon::parse($p['scheduled_date']);
            expect($d->betweenIncluded($start, $end))->toBeTrue();
        }
    });

    it('nessun post perso su periodo multi-settimana (2 settimane, ppw=5 → 10 post)', function () use ($makePosts) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(13); // 2 settimane piene

        $out = $this->scheduler->redistributePostsWithPersonas(
            $makePosts('linkedin', 10),
            ['linkedin' => 5],
            $start,
            $end,
            [],
        );

        expect($out)->toHaveCount(10);
    });

    it('l’espansione non produce due post sulla stessa data/ora per la stessa piattaforma', function () use ($makePosts) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(13);

        // Strategia con 3 slot, ppw=5 → espansione additiva di 2 slot.
        $personas = ['scheduling_strategy' => ['instagram' => ['optimal_slots' => [
            ['day' => 0, 'time' => '18:00', 'priority' => 1],
            ['day' => 2, 'time' => '18:00', 'priority' => 2],
            ['day' => 4, 'time' => '18:00', 'priority' => 3],
        ]]]];

        $out = $this->scheduler->redistributePostsWithPersonas(
            $makePosts('instagram', 10),
            ['instagram' => 5],
            $start,
            $end,
            $personas,
        );

        expect($out)->toHaveCount(10);

        $pairs = array_map(
            fn ($p) => $p['platform'] . '|' . $p['scheduled_date'] . '|' . $p['scheduled_time'],
            $out,
        );
        expect(count($pairs))->toBe(count(array_unique($pairs)));
    });

    it('non-regressione: ppw ≤ numero slot si comporta come prima (2 slot, ppw=2 → 2 post/sett)', function () use ($makePosts) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(6);

        $personas = ['scheduling_strategy' => ['linkedin' => ['optimal_slots' => [
            ['day' => 1, 'time' => '10:00', 'priority' => 1],
            ['day' => 3, 'time' => '10:00', 'priority' => 2],
        ]]]];

        $out = $this->scheduler->redistributePostsWithPersonas(
            $makePosts('linkedin', 2),
            ['linkedin' => 2],
            $start,
            $end,
            $personas,
        );

        expect($out)->toHaveCount(2);
    });
});

/**
 * BUG A (preset-aware): con un preset editoriale (es. b2b_authority) il GIORNO di
 * pubblicazione lo detta lo schedule del preset (giorno→PostType), non l'ordine di
 * arrivo dei post da Claude.
 *
 * BUG B (boundary endDate): l'ultimo giorno dell'intervallo non deve essere scartato.
 */
describe('PersonaScheduler preset-aware (b2b_authority)', function () {

    beforeEach(function () {
        $this->scheduler = new PersonaScheduler();
        $this->monday    = Carbon::parse('2026-07-20')->startOfWeek(Carbon::MONDAY); // lun 20/07
    });

    // Costruisce post con post_type esplicito (nell'ordine dato → serve a provare
    // che l'assegnazione del giorno NON dipende dall'ordine di arrivo).
    $typed = function (string $platform, array $types): array {
        $posts = [];
        foreach ($types as $i => $t) {
            $posts[] = ['platform' => $platform, 'post_type' => $t, 'content' => "{$t}-{$i}"];
        }
        return $posts;
    };

    // Indice giorno atteso per ogni tipo secondo EditorialPreset::B2BAuthority.
    $expectedDayIdx = [
        'engagement'        => 0, // lunedì
        'educational'       => 1, // martedì
        'lead_magnet'       => 2, // mercoledì
        'social_proof'      => 3, // giovedì
        'behind_the_scenes' => 4, // venerdì
    ];

    it('5 post con i 5 tipi → ogni tipo cade nel giorno previsto (ordine arrivo irrilevante)', function () use ($typed, $expectedDayIdx) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(4); // lun→ven

        // Ordine di arrivo volutamente MESCOLATO.
        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('linkedin', ['social_proof', 'behind_the_scenes', 'engagement', 'lead_magnet', 'educational']),
            ['linkedin' => 5],
            $start,
            $end,
            [],
            EditorialPreset::B2BAuthority,
        );

        expect($out)->toHaveCount(5);

        foreach ($out as $p) {
            $dayIdx = Carbon::parse($p['scheduled_date'])->dayOfWeekIso - 1;
            expect($dayIdx)->toBe($expectedDayIdx[$p['post_type']]);
        }
    });

    it('un tipo mancante → quel giorno resta vuoto, gli altri 4 corretti', function () use ($typed, $expectedDayIdx) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(4);

        // Manca behind_the_scenes (venerdì).
        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('linkedin', ['engagement', 'educational', 'lead_magnet', 'social_proof']),
            ['linkedin' => 5],
            $start,
            $end,
            [],
            EditorialPreset::B2BAuthority,
        );

        expect($out)->toHaveCount(4);

        $fridayDate = $start->copy()->addDays(4)->toDateString();
        foreach ($out as $p) {
            $dayIdx = Carbon::parse($p['scheduled_date'])->dayOfWeekIso - 1;
            expect($dayIdx)->toBe($expectedDayIdx[$p['post_type']]);
            expect($p['scheduled_date'])->not->toBe($fridayDate); // venerdì vuoto
        }
    });

    it('post con tipo NON nello schedule → assegnato a slot residuo, non scartato', function () use ($typed) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(4);

        // promotional non è nello schedule b2b; behind_the_scenes assente.
        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('linkedin', ['engagement', 'educational', 'lead_magnet', 'social_proof', 'promotional']),
            ['linkedin' => 5],
            $start,
            $end,
            [],
            EditorialPreset::B2BAuthority,
        );

        // Nessun post perso: il promotional finisce sul giorno residuo (venerdì).
        expect($out)->toHaveCount(5);

        $promo = collect($out)->firstWhere('post_type', 'promotional');
        expect($promo)->not->toBeNull();
        expect(Carbon::parse($promo['scheduled_date'])->dayOfWeekIso - 1)->toBe(4); // venerdì libero
    });

    it('preset standard/null → comportamento identico a prima del fix (non-regressione)', function () use ($typed) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(6);

        $posts = $typed('linkedin', ['a', 'b', 'c', 'd', 'e']);

        $noArg    = $this->scheduler->redistributePostsWithPersonas($posts, ['linkedin' => 5], $start, $end, []);
        $null     = $this->scheduler->redistributePostsWithPersonas($posts, ['linkedin' => 5], $start, $end, [], null);
        $standard = $this->scheduler->redistributePostsWithPersonas($posts, ['linkedin' => 5], $start, $end, [], EditorialPreset::Standard);

        // Bit-identical: date/orari uguali in tutti e tre i casi.
        $shape = fn ($r) => array_map(fn ($p) => [$p['post_type'], $p['scheduled_date'], $p['scheduled_time']], $r);
        expect($shape($null))->toBe($shape($noArg));
        expect($shape($standard))->toBe($shape($noArg));
        expect($noArg)->toHaveCount(5);
    });

    it('BUG B: intervallo lun→ven con ppw=5 → 5 post, venerdì incluso', function () use ($typed) {
        $start = $this->monday->copy();          // lun 20/07
        $end   = $start->copy()->addDays(4);      // ven 24/07 (endDate a mezzanotte)

        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('linkedin', ['engagement', 'educational', 'lead_magnet', 'social_proof', 'behind_the_scenes']),
            ['linkedin' => 5],
            $start,
            $end,
            [],
            EditorialPreset::B2BAuthority,
        );

        expect($out)->toHaveCount(5);

        $dates = array_map(fn ($p) => $p['scheduled_date'], $out);
        expect($dates)->toContain($end->toDateString()); // venerdì 24 presente
    });

    it('2 settimane con preset → 10 post, schema ripetuto correttamente', function () use ($typed, $expectedDayIdx) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(13); // 2 settimane piene

        // 2 post per tipo.
        $types = ['engagement', 'educational', 'lead_magnet', 'social_proof', 'behind_the_scenes'];
        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('linkedin', array_merge($types, $types)),
            ['linkedin' => 5],
            $start,
            $end,
            [],
            EditorialPreset::B2BAuthority,
        );

        expect($out)->toHaveCount(10);

        // Ogni post cade nel giorno-della-settimana previsto dal suo tipo.
        foreach ($out as $p) {
            $dayIdx = Carbon::parse($p['scheduled_date'])->dayOfWeekIso - 1;
            expect($dayIdx)->toBe($expectedDayIdx[$p['post_type']]);
        }
        // Due settimane distinte → due date per tipo.
        $engDates = collect($out)->where('post_type', 'engagement')->pluck('scheduled_date')->unique();
        expect($engDates)->toHaveCount(2);
    });

    // ── FIX 3: orari determinati da (preset, piattaforma, giorno) ───

    it('B2B + linkedin → orari 09:00, 08:30, 09:00, 08:30, 08:00 (lun→ven)', function () use ($typed) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(4);

        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('linkedin', ['engagement', 'educational', 'lead_magnet', 'social_proof', 'behind_the_scenes']),
            ['linkedin' => 5],
            $start,
            $end,
            [], // strategia vuota → slot di default (is_default) → il preset ne detta l'orario
            EditorialPreset::B2BAuthority,
        );

        $timeByType = collect($out)->mapWithKeys(fn ($p) => [$p['post_type'] => $p['scheduled_time']]);

        expect($timeByType['engagement'])->toBe('09:00')        // lunedì
            ->and($timeByType['educational'])->toBe('08:30')     // martedì
            ->and($timeByType['lead_magnet'])->toBe('09:00')     // mercoledì
            ->and($timeByType['social_proof'])->toBe('08:30')    // giovedì
            ->and($timeByType['behind_the_scenes'])->toBe('08:00'); // venerdì
    });

    it('B2B + instagram → orari invariati (nessun override, resta il default di piattaforma 18:00)', function () use ($typed) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(4);

        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('instagram', ['engagement', 'educational', 'lead_magnet', 'social_proof', 'behind_the_scenes']),
            ['instagram' => 5],
            $start,
            $end,
            [],
            EditorialPreset::B2BAuthority,
        );

        // Il preset è tarato su LinkedIn: su instagram slotTime() è null → resta 18:00.
        foreach ($out as $p) {
            expect($p['scheduled_time'])->toBe('18:00');
        }
    });

    it('B2B + personas custom con slot espliciti → vincono le personas sul preset', function () use ($typed) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(4);

        // Slot espliciti dell'utente (NO is_default) con orari deliberati.
        $personas = ['scheduling_strategy' => ['linkedin' => ['optimal_slots' => [
            ['day' => 0, 'time' => '07:00', 'priority' => 1],
            ['day' => 1, 'time' => '07:15', 'priority' => 2],
            ['day' => 2, 'time' => '07:30', 'priority' => 3],
            ['day' => 3, 'time' => '07:45', 'priority' => 4],
            ['day' => 4, 'time' => '07:55', 'priority' => 5],
        ]]]];

        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('linkedin', ['engagement', 'educational', 'lead_magnet', 'social_proof', 'behind_the_scenes']),
            ['linkedin' => 5],
            $start,
            $end,
            $personas,
            EditorialPreset::B2BAuthority,
        );

        $timeByType = collect($out)->mapWithKeys(fn ($p) => [$p['post_type'] => $p['scheduled_time']]);

        // Le personas custom vincono: orari utente, NON quelli del preset.
        expect($timeByType['engagement'])->toBe('07:00')
            ->and($timeByType['educational'])->toBe('07:15')
            ->and($timeByType['lead_magnet'])->toBe('07:30')
            ->and($timeByType['social_proof'])->toBe('07:45')
            ->and($timeByType['behind_the_scenes'])->toBe('07:55');
    });

    it('preset Standard → orari invariati (default di piattaforma, nessun slotTime)', function () use ($typed) {
        $start = $this->monday->copy();
        $end   = $start->copy()->addDays(4);

        $out = $this->scheduler->redistributePostsWithPersonas(
            $typed('linkedin', ['a', 'b', 'c', 'd', 'e']),
            ['linkedin' => 5],
            $start,
            $end,
            [],
            EditorialPreset::Standard,
        );

        // Standard non ha schedule → percorso classico, orario = default piattaforma.
        foreach ($out as $p) {
            expect($p['scheduled_time'])->toBe('08:00');
        }
    });
});
