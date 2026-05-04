<?php

declare(strict_types=1);

namespace App\Domain\Review\Jobs;

use App\Domain\Review\Services\GoogleReviewFetcher;
use App\Domain\Social\Exceptions\TokenExpiredException;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Services\TokenRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job di fetch delle Google review per una specifica SocialConnection.
 *
 * Dispatched da:
 *   - Scheduler globale (routes/console.php) ogni 10 min, filtrato per
 *     brand.review_fetch_interval_minutes
 *   - Bottone manuale futuro in Filament (M2)
 *
 * Su 401 esegue refresh token e ritenta una volta.
 * Su altri errori transienti: Horizon retry con backoff 60/300/900s.
 */
class FetchGoogleReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries     = 3;
    public int $timeout   = 120;
    /** @var array<int,int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly int $socialConnectionId,
    ) {
        $this->onQueue('default');
    }

    public function handle(GoogleReviewFetcher $fetcher, TokenRefreshService $tokenService): void
    {
        $connection = SocialConnection::find($this->socialConnectionId);

        if (! $connection) {
            Log::warning('[FETCH_REVIEWS] Connection not found', [
                'connection_id' => $this->socialConnectionId,
            ]);
            return;
        }

        if (! $connection->is_active) {
            Log::info('[FETCH_REVIEWS] Connection inactive, skip', [
                'connection_id' => $connection->id,
            ]);
            return;
        }

        $platformValue = $connection->platform?->value ?? $connection->platform;
        if ($platformValue !== 'google_business') {
            Log::info('[FETCH_REVIEWS] Connection platform mismatch, skip', [
                'connection_id' => $connection->id,
                'platform'      => $platformValue,
            ]);
            return;
        }

        try {
            $stats = $fetcher->fetchAndStore($connection);
            Log::info('[FETCH_REVIEWS] Done', array_merge(
                ['connection_id' => $connection->id],
                $stats,
            ));
        } catch (TokenExpiredException $e) {
            Log::warning('[FETCH_REVIEWS] Token expired, refreshing', [
                'connection_id' => $connection->id,
            ]);
            $refreshed = $tokenService->refreshIfNeeded($connection);
            // Retry una volta dopo il refresh
            $stats = $fetcher->fetchAndStore($refreshed);
            Log::info('[FETCH_REVIEWS] Done after refresh', array_merge(
                ['connection_id' => $connection->id],
                $stats,
            ));
        } catch (\Throwable $e) {
            Log::error('[FETCH_REVIEWS] Unhandled error', [
                'connection_id' => $connection->id,
                'error'         => $e->getMessage(),
            ]);
            throw $e; // Horizon retry per tries/backoff
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[FETCH_REVIEWS] Job failed permanently', [
            'connection_id' => $this->socialConnectionId,
            'error'         => $exception->getMessage(),
        ]);
    }
}
