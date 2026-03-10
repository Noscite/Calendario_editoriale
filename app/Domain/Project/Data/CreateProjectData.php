<?php

declare(strict_types=1);

namespace App\Domain\Project\Data;

use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CreateProjectData extends Data
{
    public function __construct(
        #[Required]
        public readonly int $brand_id,
        #[Required, Max(255)]
        public readonly string $name,
        #[Required, DateFormat('Y-m-d')]
        public readonly string $start_date,
        #[Required, DateFormat('Y-m-d'), AfterOrEqual('start_date')]
        public readonly string $end_date,
        /** @var array<string> */
        public readonly array $platforms = ['linkedin', 'instagram'],
        /** @var array<string, int> */
        public readonly array $posts_per_week = ['linkedin' => 3, 'instagram' => 4],
        /** @var array<string> */
        public readonly array $themes = [],
        public readonly Optional|string $brief = new Optional(),
        /** @var array<string> */
        public readonly array $reference_urls = [],
        public readonly Optional|string $target_audience = new Optional(),
        /** @var array<string> */
        public readonly array $content_pillars = [],
        /** @var array<string> */
        public readonly array $competitors = [],
        /** @var array<array<string, mixed>> */
        public readonly array $special_dates = [],
        public readonly Optional|string $custom_prompt = new Optional(),
    ) {}
}
