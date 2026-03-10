<?php

declare(strict_types=1);

namespace App\Domain\Post\Data;

use App\Domain\Post\Models\Post;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * DTO di output per un Post — corrisponde a PostResponse + PostOut (Python).
 */
final class PostData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $project_id,
        public readonly string $platform,
        public readonly ?string $scheduled_date,
        public readonly ?string $scheduled_time,
        public readonly ?string $title,
        public readonly string $content,
        public readonly ?array $hashtags,
        public readonly ?string $visual_prompt,
        public readonly ?string $visual_suggestion,
        public readonly ?string $pillar,
        public readonly ?string $post_type,
        public readonly ?string $cta,
        public readonly string $status,
        public readonly ?string $image_url,
        public readonly ?string $media_type,
        public readonly ?string $image_format,
        public readonly ?array $carousel_images,
        public readonly ?array $carousel_prompts,
        public readonly bool $is_carousel,
        public readonly ?string $content_type,
        public readonly ?string $publication_status,
        public readonly ?string $call_to_action,
        public readonly ?CarbonImmutable $created_at,
    ) {}

    public static function fromModel(Post $post): self
    {
        return new self(
            id: $post->id,
            project_id: $post->project_id,
            platform: $post->platform instanceof \BackedEnum ? $post->platform->value : ($post->platform ?? ''),
            scheduled_date: $post->scheduled_date?->toDateString(),
            scheduled_time: $post->scheduled_time,
            title: $post->title,
            content: $post->content ?? '',
            hashtags: $post->hashtags,
            visual_prompt: $post->visual_prompt,
            visual_suggestion: $post->visual_suggestion,
            pillar: $post->pillar,
            post_type: $post->post_type instanceof \BackedEnum ? $post->post_type->value : $post->post_type,
            cta: $post->cta,
            status: $post->status instanceof \BackedEnum ? $post->status->value : ($post->status ?? 'draft'),
            image_url: $post->image_url,
            media_type: $post->media_type,
            image_format: $post->image_format,
            carousel_images: $post->carousel_images,
            carousel_prompts: $post->carousel_prompts,
            is_carousel: (bool) $post->is_carousel,
            content_type: $post->content_type instanceof \BackedEnum ? $post->content_type->value : $post->content_type,
            publication_status: $post->publication_status instanceof \BackedEnum ? $post->publication_status->value : $post->publication_status,
            call_to_action: $post->call_to_action,
            created_at: $post->created_at ? CarbonImmutable::instance($post->created_at) : null,
        );
    }
}
