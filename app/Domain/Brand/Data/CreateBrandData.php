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
        public readonly Optional|string $sector,
        public readonly Optional|string $tone_of_voice,
        public readonly Optional|string|array $brand_values,
        public readonly Optional|string $description,
        #[Url]
        public readonly Optional|string $website_url,
        #[Url]
        public readonly Optional|string $linkedin_url,
        #[Url]
        public readonly Optional|string $instagram_url,
        #[Url]
        public readonly Optional|string $facebook_url,
        public readonly Optional|string $target_audience,
        public readonly Optional|string $unique_selling_points,
        public readonly Optional|string $colors,
        public readonly Optional|string $style_guide,
        public readonly Optional|array $voice_examples,
        // ── Wizard PR-1 (additivi, tutti opzionali) ─────────
        #[Max(180)]
        public readonly Optional|string $tagline,
        public readonly Optional|array $founder,
        public readonly Optional|array $narrative_assets,
        public readonly Optional|array $default_content_pillars,
        public readonly Optional|array $forbidden_topics,
    ) {}
}
