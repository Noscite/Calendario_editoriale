<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

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
    ): array {
        if (empty($posts)) {
            return [];
        }

        Log::info('[CLAUDE] Redistributing ' . count($posts) . ' posts with persona-based scheduling');

        $strategy = $personasData['scheduling_strategy'] ?? [];

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
            $postIdx = 0;

            for ($week = 0; $week < $totalWeeks; $week++) {
                $weekStart = $startDate->copy()->addWeeks($week);

                foreach ($optimalSlots as $slotNum => $slot) {
                    if ($slotNum >= $ppw || $postIdx >= count($platformPosts)) {
                        break;
                    }

                    $dayOfWeek       = $slot['day'] ?? 0;
                    $time            = $slot['time'] ?? '10:00';
                    // Python uses weekday() (Monday=0) — Carbon dayOfWeekIso is Monday=1
                    $daysUntilTarget = (($dayOfWeek) - ($weekStart->dayOfWeekIso - 1) + 7) % 7;
                    $postDate        = $weekStart->copy()->addDays($daysUntilTarget);

                    if ($postDate->lt($startDate)) {
                        $postDate->addDays(7);
                    }
                    if ($postDate->gt($endDate)) {
                        continue;
                    }

                    $p                    = $platformPosts[$postIdx];
                    $p['scheduled_date']  = $postDate->toDateString();
                    $p['scheduled_time']  = $time;
                    $redistributed[]      = $p;
                    $postIdx++;
                }
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
     * Restituisce gli slot ottimali di una piattaforma dallo scheduling_strategy.
     * Usato internamente e come utility pubblica.
     */
    public function getOptimalSlotsForPlatform(array $strategy, string $platform): array
    {
        $platStrategy = $strategy[$platform] ?? [];
        $slots        = $platStrategy['optimal_slots'] ?? [];

        if (empty($slots)) {
            return [
                ['day' => 1, 'time' => '10:00', 'priority' => 1],
                ['day' => 3, 'time' => '10:00', 'priority' => 2],
            ];
        }

        return $slots;
    }

    /**
     * Restituisce personas di default se non fornite.
     * Replica di getDefaultPersonas() da ClaudeContentGenerator.
     */
    public function getDefaultPersonas(array $platforms): array
    {
        $defaultSlots = [];
        foreach ($platforms as $platform) {
            $defaultSlots[$platform] = [
                'optimal_slots' => [
                    ['day' => 1, 'time' => '10:00', 'priority' => 1],
                    ['day' => 3, 'time' => '14:00', 'priority' => 2],
                    ['day' => 5, 'time' => '10:00', 'priority' => 3],
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
