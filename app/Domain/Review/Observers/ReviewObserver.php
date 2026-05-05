<?php

declare(strict_types=1);

namespace App\Domain\Review\Observers;

use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Jobs\ProcessAutoReplyJob;
use App\Domain\Review\Jobs\ScoreReviewJob;
use App\Domain\Review\Models\Review;

final class ReviewObserver
{
    public function created(Review $review): void
    {
        if ($review->status === ReviewStatus::New) {
            ScoreReviewJob::dispatch($review->id);
        }
    }

    /**
     * Trigger auto-reply quando lo scoring si completa
     * (status passa da 'new' a 'scored').
     *
     * Eloquent casta status a ReviewStatus, ma getOriginal() restituisce
     * il valore raw sotto al cast. Confrontiamo entrambi via ->value /
     * stringa raw per essere robusti rispetto al ciclo di vita del cast.
     */
    public function updated(Review $review): void
    {
        if (! $review->isDirty('status')) {
            return;
        }

        $current  = $review->status instanceof ReviewStatus
            ? $review->status->value
            : (string) $review->getAttribute('status');

        $original = $review->getOriginal('status');
        $original = $original instanceof ReviewStatus ? $original->value : (string) $original;

        if ($current === ReviewStatus::Scored->value && $original === ReviewStatus::New->value) {
            ProcessAutoReplyJob::dispatch($review->id);
        }
    }
}
