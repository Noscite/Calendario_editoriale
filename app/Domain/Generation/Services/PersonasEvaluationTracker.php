<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Tracking stato job EvaluateOrGeneratePersonasJob via Redis.
 *
 * Pattern simmetrico a GenerationTracker (calendario), ma stati discreti
 * invece di progress %.
 *
 * Stati: 'evaluating' → 'ready' → (su error) 'failed'.
 *
 * Il frontend polla GET /api/projects/{id}/personas-status che internamente
 * legge questa cache + ricostruisce il payload completo dal DB.
 */
final class PersonasEvaluationTracker
{
    private const KEY_PREFIX = 'personas_eval:';

    /** TTL 10 minuti — dopo, il polling fallback legge solo il DB. */
    private const TTL = 600;

    public const STATUS_EVALUATING = 'evaluating';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';

    public static function setEvaluating(int $projectId): void
    {
        self::set($projectId, ['status' => self::STATUS_EVALUATING, 'started_at' => now()->toIso8601String()]);
    }

    public static function setReady(int $projectId): void
    {
        self::set($projectId, ['status' => self::STATUS_READY, 'finished_at' => now()->toIso8601String()]);
    }

    public static function setFailed(int $projectId, ?string $reason = null): void
    {
        self::set($projectId, [
            'status'      => self::STATUS_FAILED,
            'finished_at' => now()->toIso8601String(),
            'reason'      => $reason ?? 'unknown',
        ]);
    }

    /**
     * @return array{status: string, started_at?: string, finished_at?: string, reason?: string}|null
     */
    public static function get(int $projectId): ?array
    {
        $key  = self::KEY_PREFIX . $projectId;
        $data = Redis::get($key);
        if ($data === null || $data === false) {
            return null;
        }
        $decoded = json_decode((string) $data, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function clear(int $projectId): void
    {
        Redis::del(self::KEY_PREFIX . $projectId);
    }

    public static function isEvaluating(int $projectId): bool
    {
        $state = self::get($projectId);
        return ($state['status'] ?? null) === self::STATUS_EVALUATING;
    }

    private static function set(int $projectId, array $data): void
    {
        Redis::setex(self::KEY_PREFIX . $projectId, self::TTL, json_encode($data));
    }
}
