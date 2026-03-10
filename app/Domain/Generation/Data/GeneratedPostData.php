<?php

declare(strict_types=1);

namespace App\Domain\Generation\Data;

use Spatie\LaravelData\Data;

/**
 * DTO per un post generato dall'AI — struttura intermedia prima della persistenza.
 *
 * I dati provengono dalla risposta GPT e vengono poi convertiti in CreatePostData
 * per la creazione effettiva.
 */
final class GeneratedPostData extends Data
{
    public function __construct(
        public readonly string $platform,
        public readonly string $scheduled_date,
        public readonly string $scheduled_time,
        public readonly string $content,
        public readonly array $hashtags = [],
        public readonly ?string $pillar = null,
        public readonly ?string $post_type = null,
        public readonly ?string $content_type = null,
        public readonly ?string $visual_suggestion = null,
        public readonly ?string $cta = null,
        public readonly ?string $call_to_action = null,
        public readonly ?string $title = null,
    ) {}
}
