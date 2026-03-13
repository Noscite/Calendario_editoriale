<?php

declare(strict_types=1);

namespace App\Domain\Project\Data;

use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UpdateProjectData extends Data
{
    public function __construct(
        #[Max(255)]
        public readonly Optional|string $name = new Optional(),
        #[DateFormat('Y-m-d')]
        public readonly Optional|string $start_date = new Optional(),
        #[DateFormat('Y-m-d')]
        public readonly Optional|string $end_date = new Optional(),
        /** @var array<string>|Optional */
        public readonly Optional|array $platforms = new Optional(),
        /** @var array<string, int>|Optional */
        public readonly Optional|array $posts_per_week = new Optional(),
        /** @var array<string>|Optional */
        public readonly Optional|array $themes = new Optional(),
        public readonly Optional|string $brief = new Optional(),
        /** @var array<string>|Optional */
        public readonly Optional|array $reference_urls = new Optional(),
        public readonly Optional|string $target_audience = new Optional(),
        /** @var array<string>|Optional */
        public readonly Optional|array $content_pillars = new Optional(),
        /** @var array<string>|Optional */
        public readonly Optional|array $competitors = new Optional(),
        /** @var array<array<string, mixed>>|Optional */
        public readonly Optional|array $special_dates = new Optional(),
        #[In(['draft', 'generating', 'review', 'approved', 'published'])]
        public readonly Optional|string $status = new Optional(),
        /** @var array<string, mixed>|Optional */
        public readonly Optional|array $buyer_personas = new Optional(),
        public readonly Optional|string $custom_prompt = new Optional(),
        /** @var array<string>|Optional */
        public readonly Optional|array $objectives = new Optional(),
    ) {}
}
