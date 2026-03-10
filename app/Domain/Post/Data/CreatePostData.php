<?php

declare(strict_types=1);

namespace App\Domain\Post\Data;

use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO di input per la creazione di un Post — corrisponde a PostCreate + ManualPostCreate + PostCreateInput (Python).
 */
final class CreatePostData extends Data
{
    public function __construct(
        #[Required, Exists('projects', 'id')]
        public readonly int $project_id,

        #[Required, In(['linkedin', 'instagram', 'facebook', 'google_business'])]
        public readonly string $platform,

        #[Required, DateFormat('Y-m-d')]
        public readonly string $scheduled_date,

        #[Required]
        public readonly string $content,

        public readonly string $scheduled_time = '09:00',

        public readonly Optional|string $title = new Optional(),

        /** @var array<string>|Optional */
        public readonly array $hashtags = [],

        public readonly Optional|string $pillar = new Optional(),

        public readonly string $post_type = 'educational',

        public readonly string $content_type = 'post',

        public readonly Optional|string $visual_suggestion = new Optional(),

        public readonly Optional|string $visual_prompt = new Optional(),

        public readonly Optional|string $cta = new Optional(),

        public readonly Optional|string $call_to_action = new Optional(),
    ) {}
}
