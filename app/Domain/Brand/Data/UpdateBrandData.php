<?php

declare(strict_types=1);

namespace App\Domain\Brand\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UpdateBrandData extends Data
{
    public function __construct(
        #[Max(255)]
        public readonly Optional|string $name,
        #[Max(255)]
        public readonly Optional|string $sector,
        public readonly Optional|string $tone_of_voice,
        public readonly Optional|array|null $brand_values,
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
        public readonly Optional|array|null $voice_examples,
        // ── Wizard PR-1 (additivi, tutti opzionali) ─────────
        #[Max(180)]
        public readonly Optional|string $tagline,
        public readonly Optional|array|null $founder,
        public readonly Optional|array|null $narrative_assets,
        public readonly Optional|array|null $default_content_pillars,
        public readonly Optional|array|null $forbidden_topics,
        // ── Auto-reply settings (M4) ────────────────────────
        public readonly Optional|bool $auto_reply_enabled,
        #[Min(3), Max(5)]
        public readonly Optional|int $auto_reply_min_rating,
        public readonly Optional|bool $auto_reply_only_positive_sentiment,
        #[In(['brand_default', 'empathetic', 'professional', 'solution', 'gratitude', 'formal'])]
        public readonly Optional|string $auto_reply_tone,
        public readonly Optional|bool $auto_reply_review_mode,
        #[Min(5), Max(1440)]
        public readonly Optional|int $auto_reply_delay_minutes,
    ) {}
}
