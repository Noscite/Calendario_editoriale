<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Generation\Presets\EditorialPreset;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Calcola la distribuzione temporale dei post basandosi sulle buyer personas.
 *
 * Estratto da ClaudeContentGenerator per isolare la logica
 * di scheduling ottimale e ridistribuzione dei post.
 *
 * Metodi pubblici:
 *   - redistributePostsWithPersonas() → ridistribuisce i post con scheduling personas
 *   - getOptimalSlotsForPersonas()    → restituisce gli slot ottimali per piattaforma
 *   - getDefaultPersonas()            → personas di default se non fornite
 *   - getContentMixData()             → mix contenuti default per piattaforma
 */
final class PersonaScheduler
{
    /**
     * Redistribuisce i post usando lo scheduling delle buyer personas.
     * Replica esatta di redistribute_posts_with_personas() da claude_service.py.
     */
    public function redistributePostsWithPersonas(
        array  $posts,
        array  $postsPerWeek,
        Carbon $startDate,
        Carbon $endDate,
        array  $personasData,
        ?EditorialPreset $preset = null,
    ): array {
        if (empty($posts)) {
            return [];
        }

        Log::info('[CLAUDE] Redistributing ' . count($posts) . ' posts with persona-based scheduling');

        $strategy = $personasData['scheduling_strategy'] ?? [];

        // Preset editoriale (es. b2b_authority): mappa giorno→PostType che ha
        // PRIORITÀ sugli slot persona per la scelta del GIORNO. Vuoto/null →
        // comportamento persona classico invariato.
        $scheduleByDay = $this->presetScheduleByDayIndex($preset);

        // Raggruppa post per piattaforma
        $byPlatform = [];
        foreach ($posts as $post) {
            $plat              = strtolower($post['platform'] ?? '');
            $byPlatform[$plat][] = $post;
        }

        $redistributed = [];
        $totalDays     = $startDate->diffInDays($endDate) + 1;
        $totalWeeks    = (int) ceil($totalDays / 7);

        foreach ($byPlatform as $platform => $platformPosts) {
            $optimalSlots = $this->getOptimalSlotsForPlatform($strategy, $platform);

            // Ordina per priorità
            usort($optimalSlots, fn ($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));

            $ppw     = $postsPerWeek[$platform] ?? 2;

            // FIX: il numero di post assegnati per settimana deve essere limitato da
            // $ppw, NON dal numero di slot definiti. Quando gli slot sono meno di
            // $ppw (es. strategia vuota / default insufficienti) i post eccedenti
            // sparivano silenziosamente. Espandiamo gli slot in modo additivo fino
            // a coprire $ppw prima del ciclo settimanale.
            $optimalSlots = $this->expandSlotsToMeetTarget($optimalSlots, $ppw, $platform);

            if (! empty($scheduleByDay)) {
                // ── PRESET-AWARE: il giorno lo detta lo schedule del preset ──
                $placed = $this->placePresetAware(
                    $platformPosts, $optimalSlots, $scheduleByDay, $ppw,
                    $totalWeeks, $startDate, $endDate, $platform,
                );
            } else {
                // ── PERSONA CLASSICO: comportamento invariato ──
                $placed = $this->placePersonaClassic(
                    $platformPosts, $optimalSlots, $ppw,
                    $totalWeeks, $startDate, $endDate,
                );
            }

            foreach ($placed as $p) {
                $redistributed[] = $p;
            }

            // Log difensivo: se dei post sono stati scartati (es. slot che cadono
            // oltre endDate), rendilo visibile invece di perderli in silenzio.
            if (count($placed) < count($platformPosts)) {
                Log::warning('[CLAUDE] PersonaScheduler: post scartati in redistribuzione', [
                    'platform'        => $platform,
                    'posts_received'  => count($platformPosts),
                    'posts_assigned'  => count($placed),
                    'posts_per_week'  => $ppw,
                    'slots_available' => count($optimalSlots),
                    'preset'          => $preset?->value,
                ]);
            }
        }

        // Ordina per data e ora
        usort($redistributed, function (array $a, array $b): int {
            return ($a['scheduled_date'] ?? '') <=> ($b['scheduled_date'] ?? '')
                ?: ($a['scheduled_time'] ?? '') <=> ($b['scheduled_time'] ?? '');
        });

        Log::info('[CLAUDE] Redistributed to ' . count($redistributed) . ' posts with persona scheduling');

        $platformCounts = [];
        foreach ($redistributed as $p) {
            $plat                   = $p['platform'] ?? 'unknown';
            $platformCounts[$plat] = ($platformCounts[$plat] ?? 0) + 1;
        }
        Log::info('[CLAUDE] Distribution: ' . json_encode($platformCounts));

        return $redistributed;
    }

    /**
     * Piazzamento persona classico (nessun preset): un post per slot, in ordine
     * di arrivo da Claude, fino a $ppw per settimana. Comportamento identico alla
     * versione pre-preset (bit-identical per progetti senza preset).
     *
     * @return list<array>
     */
    private function placePersonaClassic(
        array  $platformPosts,
        array  $optimalSlots,
        int    $ppw,
        int    $totalWeeks,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        $placed  = [];
        $postIdx = 0;

        for ($week = 0; $week < $totalWeeks; $week++) {
            $weekStart      = $startDate->copy()->addWeeks($week);
            $placedThisWeek = 0;

            foreach ($optimalSlots as $slot) {
                // Uscita basata sui post rimanenti e su $ppw, non sull'indice di
                // slot: esattamente $ppw post per settimana (o meno se i post
                // disponibili finiscono prima).
                if ($placedThisWeek >= $ppw || $postIdx >= count($platformPosts)) {
                    break;
                }

                $postDate = $this->computePostDate($weekStart, (int) ($slot['day'] ?? 0), $startDate, $endDate);
                if ($postDate === null) {
                    continue;
                }

                $p                   = $platformPosts[$postIdx];
                $p['scheduled_date'] = $postDate->toDateString();
                $p['scheduled_time'] = $slot['time'] ?? '10:00';
                $placed[]            = $p;
                $postIdx++;
                $placedThisWeek++;
            }
        }

        return $placed;
    }

    /**
     * Piazzamento preset-aware: il GIORNO lo detta lo schedule del preset
     * (giorno→PostType); l'orario resta quello dello slot persona per quel giorno
     * (fallback: orario di default della piattaforma).
     *
     * Regole:
     *  - Per ogni settimana e per ogni giorno dello schedule, assegna il primo
     *    post non ancora piazzato con post_type corrispondente.
     *  - I tipi non presenti nello schedule, o i post eccedenti, sono piazzati
     *    sugli slot residui (giorni non usati quella settimana) SENZA scartarli.
     *  - Se un tipo previsto non ha un post corrispondente, quel giorno resta
     *    vuoto: nessun post inventato.
     *  - Mai più di $ppw post per settimana.
     *
     * @param array<int, \App\Domain\Post\Enums\PostType> $scheduleByDay  [dayIdx => PostType]
     * @return list<array>
     */
    private function placePresetAware(
        array  $platformPosts,
        array  $optimalSlots,
        array  $scheduleByDay,
        int    $ppw,
        int    $totalWeeks,
        Carbon $startDate,
        Carbon $endDate,
        string $platform,
    ): array {
        $placed    = [];
        $assigned  = array_fill(0, count($platformPosts), false);
        $timeByDay = $this->personaTimeByDayIndex($optimalSlots);

        for ($week = 0; $week < $totalWeeks; $week++) {
            $weekStart      = $startDate->copy()->addWeeks($week);
            $placedThisWeek = 0;
            $usedDays       = [];

            // ── Passata PRESET: giorno → tipo ──
            foreach ($scheduleByDay as $dayIdx => $postType) {
                if ($placedThisWeek >= $ppw) {
                    break;
                }
                $idx = $this->firstUnassignedByType($platformPosts, $assigned, $postType->value);
                if ($idx === null) {
                    continue; // nessun post di questo tipo → giorno vuoto
                }
                $postDate = $this->computePostDate($weekStart, $dayIdx, $startDate, $endDate);
                if ($postDate === null) {
                    continue;
                }

                $p                   = $platformPosts[$idx];
                $p['scheduled_date'] = $postDate->toDateString();
                $p['scheduled_time'] = $timeByDay[$dayIdx] ?? $this->defaultTimeForPlatform($platform);
                $placed[]            = $p;
                $assigned[$idx]      = true;
                $placedThisWeek++;
                $usedDays[$dayIdx]   = true;
            }

            // ── Passata FALLBACK: post rimanenti su slot residui (giorni liberi) ──
            foreach ($optimalSlots as $slot) {
                if ($placedThisWeek >= $ppw) {
                    break;
                }
                $idx = $this->firstUnassigned($assigned);
                if ($idx === null) {
                    break; // niente più post da piazzare
                }
                $dayIdx = (int) ($slot['day'] ?? 0);
                if (isset($usedDays[$dayIdx])) {
                    continue; // giorno già occupato questa settimana
                }
                $postDate = $this->computePostDate($weekStart, $dayIdx, $startDate, $endDate);
                if ($postDate === null) {
                    continue;
                }

                $p                   = $platformPosts[$idx];
                $p['scheduled_date'] = $postDate->toDateString();
                $p['scheduled_time'] = $slot['time'] ?? $this->defaultTimeForPlatform($platform);
                $placed[]            = $p;
                $assigned[$idx]      = true;
                $placedThisWeek++;
                $usedDays[$dayIdx]   = true;
            }
        }

        return $placed;
    }

    /**
     * Calcola la data di pubblicazione per un dato indice di giorno (0=lun … 6=dom)
     * dentro la settimana $weekStart. Ritorna null se cade fuori [startDate, endDate].
     *
     * BUG B: il confronto è solo sulla parte DATA — $endDate arriva a mezzanotte
     * dal cast `date` del model, mentre $postDate potrebbe avere una componente
     * oraria, facendo scartare erroneamente l'ultimo giorno dell'intervallo.
     */
    private function computePostDate(Carbon $weekStart, int $dayIdx, Carbon $startDate, Carbon $endDate): ?Carbon
    {
        // Python uses weekday() (Monday=0) — Carbon dayOfWeekIso is Monday=1
        $daysUntilTarget = ($dayIdx - ($weekStart->dayOfWeekIso - 1) + 7) % 7;
        $postDate        = $weekStart->copy()->addDays($daysUntilTarget);

        if ($postDate->toDateString() < $startDate->toDateString()) {
            $postDate->addDays(7);
        }
        if ($postDate->toDateString() > $endDate->toDateString()) {
            return null;
        }

        return $postDate;
    }

    /**
     * Converte lo schedule del preset (chiavi giorno in italiano → PostType) in una
     * mappa [indiceGiorno => PostType] ordinata per giorno (0=lun … 6=dom).
     * Ritorna [] se il preset è null o ha schedule vuoto.
     *
     * @return array<int, \App\Domain\Post\Enums\PostType>
     */
    private function presetScheduleByDayIndex(?EditorialPreset $preset): array
    {
        $schedule = $preset?->weeklySchedule() ?? [];
        if (empty($schedule)) {
            return [];
        }

        $dayIndex = [
            'lunedì'    => 0,
            'martedì'   => 1,
            'mercoledì' => 2,
            'giovedì'   => 3,
            'venerdì'   => 4,
            'sabato'    => 5,
            'domenica'  => 6,
        ];

        $byDay = [];
        foreach ($schedule as $dayName => $postType) {
            $idx = $dayIndex[mb_strtolower(trim((string) $dayName))] ?? null;
            if ($idx !== null) {
                $byDay[$idx] = $postType;
            }
        }

        ksort($byDay);

        return $byDay;
    }

    /**
     * Mappa [indiceGiorno => orario] a partire dagli slot persona. In caso di più
     * slot sullo stesso giorno vince il primo (già ordinati per priorità).
     *
     * @return array<int, string>
     */
    private function personaTimeByDayIndex(array $slots): array
    {
        $byDay = [];
        foreach ($slots as $slot) {
            $dayIdx = (int) ($slot['day'] ?? 0);
            $time   = trim((string) ($slot['time'] ?? ''));
            if ($time !== '' && ! isset($byDay[$dayIdx])) {
                $byDay[$dayIdx] = $time;
            }
        }

        return $byDay;
    }

    /**
     * Indice del primo post non ancora assegnato con post_type corrispondente.
     */
    private function firstUnassignedByType(array $posts, array $assigned, string $type): ?int
    {
        foreach ($posts as $i => $post) {
            if (($assigned[$i] ?? false) === true) {
                continue;
            }
            if ((string) ($post['post_type'] ?? '') === $type) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Indice del primo post non ancora assegnato (qualsiasi tipo).
     */
    private function firstUnassigned(array $assigned): ?int
    {
        foreach ($assigned as $i => $isAssigned) {
            if ($isAssigned !== true) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Restituisce gli slot ottimali di una piattaforma dallo scheduling_strategy.
     * Usato internamente e come utility pubblica.
     */
    public function getOptimalSlotsForPlatform(array $strategy, string $platform): array
    {
        $platStrategy = $strategy[$platform] ?? [];
        $slots        = $platStrategy['optimal_slots'] ?? [];

        if (empty($slots)) {
            // Fallback: 5 slot feriali (lun–ven) invece di 2, così una piattaforma
            // con strategia vuota può comunque coprire fino a $ppw = 5 senza
            // dipendere dall'espansione.
            $time = $this->defaultTimeForPlatform($platform);

            return [
                ['day' => 0, 'time' => $time, 'priority' => 1],
                ['day' => 1, 'time' => $time, 'priority' => 2],
                ['day' => 2, 'time' => $time, 'priority' => 3],
                ['day' => 3, 'time' => $time, 'priority' => 4],
                ['day' => 4, 'time' => $time, 'priority' => 5],
            ];
        }

        return $slots;
    }

    /**
     * Espande la lista di slot in modo puramente ADDITIVO finché non raggiunge
     * $target elementi. Gli slot già definiti (dalla strategia personas) non
     * vengono modificati né riordinati.
     *
     * Regole:
     *  - Distribuisce sui giorni feriali (0=lun … 4=ven) non ancora occupati, in ordine.
     *  - Solo se $target > 5 usa anche sabato/domenica (day 5, 6).
     *  - Ogni nuovo slot riusa l'orario dello slot esistente a priorità più alta;
     *    se non ce n'è, usa '10:00'.
     *  - Le priorità dei nuovi slot proseguono incrementali dalla massima esistente.
     *  - Ogni coppia (day, time) resta unica per evitare due post sulla stessa
     *    data/ora nella stessa settimana; se i giorni si esauriscono (target molto
     *    alto) sfalsa l'orario di un'ora per garantire l'unicità.
     */
    private function expandSlotsToMeetTarget(array $slots, int $target, string $platform): array
    {
        if (count($slots) >= $target) {
            return $slots;
        }

        $usedPairs   = [];
        $usedDays    = [];
        $maxPriority = 0;
        foreach ($slots as $s) {
            $day  = (int) ($s['day'] ?? 0);
            $time = (string) ($s['time'] ?? '');
            $usedDays[$day]              = true;
            $usedPairs[$day . '@' . $time] = true;
            $maxPriority = max($maxPriority, (int) ($s['priority'] ?? 0));
        }

        $refTime = $this->highestPrioritySlotTime($slots);

        // Giorni candidati: feriali prima; weekend solo se target lo richiede.
        $candidateDays = ($target > 5) ? [0, 1, 2, 3, 4, 5, 6] : [0, 1, 2, 3, 4];

        $expanded = $slots; // additivo, ordine originale preservato
        $priority = $maxPriority;

        // Prima passata: giorni feriali/candidati non ancora occupati, in ordine.
        foreach ($candidateDays as $day) {
            if (count($expanded) >= $target) {
                break;
            }
            if (isset($usedDays[$day])) {
                continue;
            }
            $priority++;
            $expanded[]                       = ['day' => $day, 'time' => $refTime, 'priority' => $priority];
            $usedDays[$day]                   = true;
            $usedPairs[$day . '@' . $refTime] = true;
        }

        // Edge case (target > giorni disponibili, es. $ppw molto alto): aggiungi
        // ulteriori slot mantenendo (day, time) unico, sfalsando l'orario di un'ora.
        $hourOffset = 1;
        while (count($expanded) < $target) {
            $added = false;
            foreach ($candidateDays as $day) {
                if (count($expanded) >= $target) {
                    break;
                }
                $time = $this->shiftTimeByHours($refTime, $hourOffset);
                $key  = $day . '@' . $time;
                if (isset($usedPairs[$key])) {
                    continue;
                }
                $priority++;
                $expanded[]       = ['day' => $day, 'time' => $time, 'priority' => $priority];
                $usedPairs[$key]  = true;
                $added            = true;
            }
            $hourOffset++;
            if (! $added && $hourOffset > 24) {
                break; // safety: evita loop infinito in scenari degeneri
            }
        }

        return $expanded;
    }

    /**
     * Orario dello slot a priorità più alta (valore priority più basso).
     * Fallback '10:00' se nessuno slot ha un orario valido.
     */
    private function highestPrioritySlotTime(array $slots): string
    {
        $bestPriority = null;
        $bestTime     = '';
        foreach ($slots as $s) {
            $time = trim((string) ($s['time'] ?? ''));
            if ($time === '') {
                continue;
            }
            $pr = (int) ($s['priority'] ?? PHP_INT_MAX);
            if ($bestPriority === null || $pr < $bestPriority) {
                $bestPriority = $pr;
                $bestTime     = $time;
            }
        }

        return $bestTime !== '' ? $bestTime : '10:00';
    }

    /**
     * Sfalsa un orario "HH:MM" di N ore (mod 24), preservando i minuti.
     * Usato solo nell'edge case di espansione con target > giorni disponibili.
     */
    private function shiftTimeByHours(string $time, int $hours): string
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '00');
        $newHour = ((int) $h + $hours) % 24;

        return sprintf('%02d:%02d', $newHour, (int) $m);
    }

    /**
     * Orario di default sensato per piattaforma, coerente con i default di
     * ClaudeContentGenerator. Piattaforme non note → 10:00.
     */
    private function defaultTimeForPlatform(string $platform): string
    {
        return match (strtolower($platform)) {
            'linkedin'        => '08:00',
            'facebook'        => '13:00',
            'instagram'       => '18:00',
            'google_business' => '10:00',
            default           => '10:00',
        };
    }

    /**
     * Restituisce personas di default se non fornite.
     * Replica di getDefaultPersonas() da ClaudeContentGenerator.
     */
    public function getDefaultPersonas(array $platforms): array
    {
        $defaultSlots = [];
        foreach ($platforms as $platform) {
            // 5 slot feriali (lun–ven) con orario coerente alla piattaforma, così
            // i default coprono fino a $ppw = 5 senza dover ricorrere all'espansione.
            $time = $this->defaultTimeForPlatform($platform);

            $defaultSlots[$platform] = [
                'optimal_slots' => [
                    ['day' => 0, 'time' => $time, 'priority' => 1],
                    ['day' => 1, 'time' => $time, 'priority' => 2],
                    ['day' => 2, 'time' => $time, 'priority' => 3],
                    ['day' => 3, 'time' => $time, 'priority' => 4],
                    ['day' => 4, 'time' => $time, 'priority' => 5],
                ],
                'avoid' => [],
            ];
        }

        return [
            'personas' => [
                [
                    'name'         => 'Professionista Target',
                    'weight'       => 1.0,
                    'demographics' => [
                        'age_range' => '30-50',
                        'role'      => 'Decision maker',
                        'location'  => 'Italia',
                    ],
                    'pain_points' => ['Mancanza di tempo', 'Bisogno di efficienza'],
                    'interests'   => ['Innovazione', 'Best practices'],
                ],
            ],
            'scheduling_strategy' => $defaultSlots,
        ];
    }

    /**
     * Ritorna mix contenuti di default per piattaforma.
     * Replica di getContentMixData() da ClaudeContentGenerator.
     */
    public function getContentMixData(array $platforms, array $brandInfo): array
    {
        $defaults = [
            'instagram' => [
                'platform'                 => 'instagram',
                'source'                   => 'default',
                'supports_stories'         => true,
                'supports_reels'           => true,
                'recommended_weekly_total' => 7,
                'format_mix'               => ['post_percentage' => 45, 'story_percentage' => 35, 'reel_percentage' => 20],
                'format_weekly_count'      => ['posts' => 3, 'stories' => 3, 'reels' => 1],
                'best_content_ideas'       => ['posts' => [], 'stories' => [], 'reels' => []],
            ],
            'facebook' => [
                'platform'                 => 'facebook',
                'source'                   => 'default',
                'supports_stories'         => true,
                'supports_reels'           => true,
                'recommended_weekly_total' => 5,
                'format_mix'               => ['post_percentage' => 60, 'story_percentage' => 25, 'reel_percentage' => 15],
                'format_weekly_count'      => ['posts' => 3, 'stories' => 1, 'reels' => 1],
                'best_content_ideas'       => ['posts' => [], 'stories' => [], 'reels' => []],
            ],
            'linkedin' => [
                'platform'                 => 'linkedin',
                'source'                   => 'default',
                'supports_stories'         => false,
                'supports_reels'           => false,
                'recommended_weekly_total' => 4,
                'format_mix'               => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'      => ['posts' => 4, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'       => ['posts' => []],
            ],
            'tiktok' => [
                'platform'                 => 'tiktok',
                'source'                   => 'default',
                'supports_stories'         => false,
                'supports_reels'           => true,
                'recommended_weekly_total' => 5,
                'format_mix'               => ['post_percentage' => 30, 'story_percentage' => 0, 'reel_percentage' => 70],
                'format_weekly_count'      => ['posts' => 2, 'stories' => 0, 'reels' => 3],
                'best_content_ideas'       => ['posts' => [], 'reels' => []],
            ],
            'twitter' => [
                'platform'                 => 'twitter',
                'source'                   => 'default',
                'supports_stories'         => false,
                'supports_reels'           => false,
                'recommended_weekly_total' => 7,
                'format_mix'               => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'      => ['posts' => 7, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'       => ['posts' => []],
            ],
        ];

        $result = [];
        foreach ($platforms as $platform) {
            $key          = strtolower($platform);
            $result[$key] = $defaults[$key] ?? [
                'platform'                 => $key,
                'source'                   => 'default',
                'supports_stories'         => false,
                'supports_reels'           => false,
                'recommended_weekly_total' => 5,
                'format_mix'               => ['post_percentage' => 100, 'story_percentage' => 0, 'reel_percentage' => 0],
                'format_weekly_count'      => ['posts' => 5, 'stories' => 0, 'reels' => 0],
                'best_content_ideas'       => ['posts' => []],
            ];
        }

        return $result;
    }
}
