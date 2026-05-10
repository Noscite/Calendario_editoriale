<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CreateCampaignData extends Data
{
    public function __construct(
        #[Max(255)]
        public readonly string $name,
        #[Max(500)]
        public readonly Optional|string|null $description,
        public readonly Optional|string|null $brief,
        public readonly Optional|string|null $start_date,
        public readonly Optional|string|null $end_date,
        /** @var int[] */
        public readonly Optional|array|null $brand_ids,
    ) {}
}
