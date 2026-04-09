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
        public readonly Optional|string|null $content = new Optional(),
        public readonly Optional|string|null $title = new Optional(),
        /** @var array<string>|Optional|null */
        public readonly Optional|array|null $hashtags = new Optional(),
        #[DateFormat('Y-m-d')]
        public readonly Optional|string|null $scheduled_date = new Optional(),
        public readonly Optional|string|null $scheduled_time = new Optional(),
        #[In(['linkedin', 'instagram', 'facebook', 'google_business'])]
        public readonly Optional|string|null $platform = new Optional(),
        public readonly Optional|string|null $visual_prompt = new Optional(),
        public readonly Optional|string|null $visual_suggestion = new Optional(),
        #[In(['draft', 'review', 'approved', 'published', 'rejected'])]
        public readonly Optional|string|null $status = new Optional(),
        public readonly Optional|string|null $pillar = new Optional(),
        public readonly Optional|string|null $post_type = new Optional(),
        public readonly Optional|string|null $content_type = new Optional(),
        public readonly Optional|string|null $cta = new Optional(),
        public readonly Optional|string|null $call_to_action = new Optional(),
        public readonly Optional|string|null $publication_status = new Optional(),
    ) {}
}
