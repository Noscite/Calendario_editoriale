<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Tracking stato generazione via Redis (shared tra workers/web).
 *
 * Replica esatta di generation_tracker.py:
 *   _generation_status[project_id] = {
 *       "current_batch": current_batch,
 *       "total_batches": total_batches,
 *       "percent": percent
 *   }
 *
 * Il Python usa un dict in-memory (singolo processo). Laravel usa Redis
 * perché il Job gira su un worker Horizon separato dal web process.
 */
final class GenerationTracker
{
    /** Prefisso chiave Redis. */
    private const KEY_PREFIX = 'generation_status:';

    /** TTL in secondi (30 minuti — un calendario non può impiegare di più). */
    private const TTL = 1800;

    /**
     * update_generation_status() — aggiorna progresso batch.
     */
    public static function update(int $projectId, int $currentBatch, int $totalBatches, int $percent): void
    {
        $key = self::KEY_PREFIX . $projectId;

        $data = [
            'current_batch' => $currentBatch,
            'total_batches' => $totalBatches,
            'percent'       => $percent,
        ];

        Redis::setex($key, self::TTL, json_encode($data));

        Log::info("[TRACKER] Project {$projectId}: Batch {$currentBatch}/{$totalBatches} — {$percent}%");
    }

    /**
     * get_generation_status_cache() — recupera stato corrente.
     *
     * @return array{current_batch: int, total_batches: int, percent: int}|null
     */
    public static function get(int $projectId): ?array
    {
        $key  = self::KEY_PREFIX . $projectId;
        $data = Redis::get($key);

        if ($data === null || $data === false) {
            return null;
        }

        return json_decode((string) $data, true);
    }

    /**
     * clear_generation_status() — pulisci al termine della generazione.
     */
    public static function clear(int $projectId): void
    {
        $key = self::KEY_PREFIX . $projectId;
        Redis::del($key);

        Log::info("[TRACKER] Cleared status for project {$projectId}");
    }
}
