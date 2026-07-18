<?php

declare(strict_types=1);

namespace App\Domain\Project\Data;

use App\Domain\Generation\Presets\EditorialPreset;
use App\Domain\Project\Models\Project;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class ProjectData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $brand_id,
        public readonly string $name,
        public readonly string $start_date,
        public readonly string $end_date,
        public readonly array $platforms,
        public readonly array $posts_per_week,
        public readonly array $themes,
        public readonly ?string $brief,
        public readonly array $reference_urls,
        public readonly ?string $target_audience,
        public readonly array $content_pillars,
        public readonly array $competitors,
        public readonly array $special_dates,
        public readonly string $status,
        public readonly ?array $buyer_personas,
        public readonly ?array $objectives,
        public readonly ?string $custom_prompt,
        public readonly ?string $editorial_preset,
        public readonly CarbonImmutable $created_at,
    ) {}

    public static function fromModel(Project $project): self
    {
        return new self(
            id: $project->id,
            brand_id: $project->brand_id,
            name: $project->name,
            start_date: $project->start_date?->toDateString() ?? '',
            end_date: $project->end_date?->toDateString() ?? '',
            platforms: $project->platforms ?? [],
            posts_per_week: $project->posts_per_week ?? [],
            themes: $project->themes ?? [],
            brief: $project->brief,
            reference_urls: $project->reference_urls ?? [],
            target_audience: $project->target_audience,
            content_pillars: $project->content_pillars ?? [],
            competitors: $project->competitors ?? [],
            special_dates: $project->special_dates ?? [],
            status: $project->status instanceof \BackedEnum ? $project->status->value : ($project->status ?? 'draft'),
            buyer_personas: $project->buyer_personas,
            objectives: $project->objectives,
            custom_prompt: $project->custom_prompt,
            editorial_preset: $project->editorial_preset instanceof \BackedEnum
                ? $project->editorial_preset->value
                : ($project->editorial_preset ?? EditorialPreset::Standard->value),
            created_at: CarbonImmutable::instance($project->created_at),
        );
    }
}
