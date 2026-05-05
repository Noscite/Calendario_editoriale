<?php

declare(strict_types=1);

namespace App\Domain\Review\Contracts;

use App\Domain\Review\Enums\ReplyTone;
use App\Domain\Review\Models\Review;

interface ReviewReplyGeneratorInterface
{
    /**
     * Genera la bozza di reply con metadati di provenienza.
     *
     * @return array{body:string,tone_used:string,marketing_strategy:string,kb_chunks_used:array<int,int>,generated_by_model:string,input_tokens:?int,output_tokens:?int}
     */
    public function generate(
        Review $review,
        ReplyTone $tone = ReplyTone::BrandDefault,
        ?string $marketingStrategyOverride = null,
    ): array;
}
