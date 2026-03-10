<?php

declare(strict_types=1);

namespace App\Domain\Post\Data;

use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO di input per l'aggiornamento di un Post — corrisponde a PostUpdate + PostUpdateInput (Python).
 */
final class UpdatePostData extends Data
{
    public function __construct(
        public readonly Optional|string $content = new Optional(),
        public readonly Optional|string $title = new Optional(),
        /** @var array<string>|Optional */
        public readonly Optional|array $hashtags = new Optional(),
        #[DateFormat('Y-m-d')]
        public readonly Optional|string $scheduled_date = new Optional(),
        public readonly Optional|string $scheduled_time = new Optional(),
        #[In(['linkedin', 'instagram', 'facebook', 'google_business'])]
        public readonly Optional|string $platform = new Optional(),
        public readonly Optional|string $visual_prompt = new Optional(),
        public readonly Optional|string $visual_suggestion = new Optional(),
        #[In(['draft', 'review', 'approved', 'published', 'rejected'])]
        public readonly Optional|string $status = new Optional(),
        public readonly Optional|string $pillar = new Optional(),
        public readonly Optional|string $post_type = new Optional(),
        public readonly Optional|string $content_type = new Optional(),
        public readonly Optional|string $cta = new Optional(),
        public readonly Optional|string $call_to_action = new Optional(),
    ) {}
}
