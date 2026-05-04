<?php

declare(strict_types=1);

namespace App\Domain\Review\Observers;

use App\Domain\Review\Enums\ReviewStatus;
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
}
