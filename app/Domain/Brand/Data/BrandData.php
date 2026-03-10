<?php

declare(strict_types=1);

namespace App\Domain\Brand\Data;

use App\Domain\Brand\Models\Brand;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class BrandData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $organization_id,
        public readonly string $name,
        public readonly ?string $sector,
        public readonly ?string $tone_of_voice,
        public readonly ?string $brand_values,
        public readonly ?string $description,
        public readonly ?string $website_url,
        public readonly ?string $linkedin_url,
        public readonly ?string $instagram_url,
        public readonly ?string $facebook_url,
        public readonly ?string $target_audience,
        public readonly ?string $unique_selling_points,
        public readonly ?string $colors,
        public readonly ?string $style_guide,
        public readonly CarbonImmutable $created_at,
        public readonly ?int $projects_count = null,
        public readonly ?int $posts_count = null,
    ) {}

    public static function fromModel(Brand $brand): self
    {
        return new self(
            id: $brand->id,
            organization_id: $brand->organization_id,
            name: $brand->name,
            sector: $brand->sector,
            tone_of_voice: $brand->tone_of_voice,
            brand_values: $brand->brand_values,
            description: $brand->description,
            website_url: $brand->website_url,
            linkedin_url: $brand->linkedin_url,
            instagram_url: $brand->instagram_url,
            facebook_url: $brand->facebook_url,
            target_audience: $brand->target_audience,
            unique_selling_points: $brand->unique_selling_points,
            colors: $brand->colors,
            style_guide: $brand->style_guide,
            created_at: CarbonImmutable::instance($brand->created_at),
            projects_count: $brand->projects_count ?? null,
            posts_count: $brand->posts_count ?? null,
        );
    }
}
