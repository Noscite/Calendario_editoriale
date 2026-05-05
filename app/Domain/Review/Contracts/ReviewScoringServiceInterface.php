<?php

declare(strict_types=1);

namespace App\Domain\Review\Contracts;

use App\Domain\Review\Models\Review;

interface ReviewScoringServiceInterface
{
    /**
     * Analizza una review e restituisce lo scoring strutturato.
     *
     * @return array{sentiment:string,urgency:string,topics:array<int,string>,is_fake_suspect:bool,marketing_opportunity:string,rationale:string}
     */
    public function score(Review $review): array;
}
