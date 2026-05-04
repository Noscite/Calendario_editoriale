<?php

declare(strict_types=1);

namespace App\Domain\Review\Jobs;

use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Services\ReviewScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job di scoring asincrono di una review.
 *
 * Dispatched dal ReviewObserver alla creazione di review con status=new.
 * Idempotente: salta review già scored.
 */
class ScoreReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries     = 3;
    public int $timeout   = 60;
    /** @var array<int,int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly int $reviewId,
    ) {
        $this->onQueue('default');
    }

    public function handle(ReviewScoringService $scorer): void
    {
        $review = Review::withoutGlobalScope('organization')->find($this->reviewId);

        if (! $review) {
            Log::warning('[REVIEW_SCORE] Review non trovata', ['review_id' => $this->reviewId]);
            return;
        }

        if ($review->scored_at !== null) {
            Log::info('[REVIEW_SCORE] Review già scored, skip', ['review_id' => $review->id]);
            return;
        }

        try {
            $result = $scorer->score($review);

            $review->update([
                'sentiment'             => $result['sentiment'],
                'urgency'               => $result['urgency'],
                'topics'                => $result['topics'],
                'is_fake_suspect'       => $result['is_fake_suspect'],
                'marketing_opportunity' => $result['marketing_opportunity'],
                'scoring_rationale'     => $result['rationale'],
                'scored_by_model'       => ReviewScoringService::MODEL,
                'scored_at'             => now(),
                'status'                => ReviewStatus::Scored,
            ]);

            Log::info('[REVIEW_SCORE] OK', [
                'review_id'             => $review->id,
                'sentiment'             => $result['sentiment'],
                'urgency'               => $result['urgency'],
                'marketing_opportunity' => $result['marketing_opportunity'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[REVIEW_SCORE] Scoring fallito', [
                'review_id' => $review->id,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[REVIEW_SCORE] Job failed permanently', [
            'review_id' => $this->reviewId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
