<?php

declare(strict_types=1);

namespace App\Domain\Brand\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CreateBrandData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        #[Max(255)]
        public readonly Optional|string|null $sector,
        public readonly Optional|string|null $tone_of_voice,
        public readonly Optional|array|null $brand_values,
        public readonly Optional|string|null $description,
        #[Url]
        public readonly Optional|string|null $website_url,
        #[Url]
        public readonly Optional|string|null $linkedin_url,
        #[Url]
        public readonly Optional|string|null $instagram_url,
        #[Url]
        public readonly Optional|string|null $facebook_url,
        public readonly Optional|string|null $target_audience,
        public readonly Optional|string|null $unique_selling_points,
        public readonly Optional|string|null $colors,
        public readonly Optional|string|null $style_guide,
        public readonly Optional|array|null $voice_examples,
        // ── Wizard PR-1 (additivi, tutti opzionali) ─────────
        #[Max(180)]
        public readonly Optional|string|null $tagline,
        public readonly Optional|array|null $founder,
        public readonly Optional|array|null $narrative_assets,
        public readonly Optional|array|null $default_content_pillars,
        public readonly Optional|array|null $forbidden_topics,
    ) {}
}
