<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Tracker dello stato di avanzamento della pipeline di generazione del
 * calendario editoriale. Condiviso tra GenerateCalendarJob,
 * SyncTerritorialEventsJob e GenerateTerritorialPostsJob.
 *
 * Le chiavi vivono in Redis con TTL di 2h. Lette via endpoint API per
 * il polling frontend.
 */
class GenerationProgressService
{
    private const CACHE_TTL_SECONDS = 7200; // 2h: copre anche generazioni molto lunghe

    public function start(int $projectId): void
    {
        $payload = [
            'status'              => 'generating',
            'started_at'          => now()->toIso8601String(),
            'completed_at'        => null,
            'failed_at'           => null,
            'failed_reason'       => null,
            'phases'              => [
                'calendar_base' => [
                    'status'      => 'pending',
                    'posts_done'  => 0,
                    'posts_total' => null,
                ],
                'territorial_sync' => [
                    'status'         => 'pending',
                    'events_synced'  => 0,
                ],
                'territorial_posts' => [
                    'status'               => 'pending',
                    'events_total'         => null,
                    'events_completed'     => 0,
                    'posts_done'           => 0,
                    'posts_total_expected' => null,
                ],
            ],
        ];

        Cache::put($this->key($projectId), $payload, self::CACHE_TTL_SECONDS);
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    public function updatePhase(int $projectId, string $phaseKey, array $updates): void
    {
        $payload = Cache::get($this->key($projectId));
        if (! $payload) {
            return;
        }

        $payload['phases'][$phaseKey] = array_merge(
            $payload['phases'][$phaseKey] ?? [],
            $updates
        );

        Cache::put($this->key($projectId), $payload, self::CACHE_TTL_SECONDS);
    }

    public function incrementPhaseCounter(int $projectId, string $phaseKey, string $field, int $by = 1): void
    {
        $payload = Cache::get($this->key($projectId));
        if (! $payload) {
            return;
        }

        $current = $payload['phases'][$phaseKey][$field] ?? 0;
        $payload['phases'][$phaseKey][$field] = $current + $by;

        Cache::put($this->key($projectId), $payload, self::CACHE_TTL_SECONDS);
    }

    public function complete(int $projectId): void
    {
        $payload = Cache::get($this->key($projectId));
        if (! $payload) {
            return;
        }

        $payload['status']       = 'completed';
        $payload['completed_at'] = now()->toIso8601String();

        Cache::put($this->key($projectId), $payload, self::CACHE_TTL_SECONDS);
    }

    public function fail(int $projectId, string $reason): void
    {
        $payload = Cache::get($this->key($projectId)) ?? ['phases' => []];

        $payload['status']        = 'failed';
        $payload['failed_at']     = now()->toIso8601String();
        $payload['failed_reason'] = mb_substr($reason, 0, 500);

        Cache::put($this->key($projectId), $payload, self::CACHE_TTL_SECONDS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $projectId): ?array
    {
        return Cache::get($this->key($projectId));
    }

    public function clear(int $projectId): void
    {
        Cache::forget($this->key($projectId));
    }

    private function key(int $projectId): string
    {
        return "generation:progress:{$projectId}";
    }
}
