<?php

declare(strict_types=1);

namespace App\Domain\Project\Data;

use App\Domain\Generation\Presets\EditorialPreset;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CreateProjectData extends Data
{
    public function __construct(
        #[Required]
        public readonly int $brand_id,
        #[Required, Max(255)]
        public readonly string $name,
        #[Required, DateFormat('Y-m-d')]
        public readonly string $start_date,
        #[Required, DateFormat('Y-m-d'), AfterOrEqual('start_date')]
        public readonly string $end_date,
        /** @var array<string>|null */
        public readonly ?array $platforms = ['linkedin', 'instagram'],
        /** @var array<string, int>|null */
        public readonly ?array $posts_per_week = ['linkedin' => 3, 'instagram' => 4],
        /** @var array<string>|null */
        public readonly ?array $themes = null,
        public readonly Optional|string|null $brief = new Optional(),
        /** @var array<string>|null */
        public readonly ?array $reference_urls = null,
        public readonly Optional|string|null $target_audience = new Optional(),
        /** @var array<string>|null */
        public readonly ?array $content_pillars = null,
        /** @var array<string>|null */
        public readonly ?array $competitors = null,
        /** @var array<array<string, mixed>>|null */
        public readonly ?array $special_dates = null,
        public readonly Optional|string|null $custom_prompt = new Optional(),
        #[Enum(EditorialPreset::class)]
        public readonly ?string $editorial_preset = 'standard',
    ) {}

    public function toArray(): array
    {
        return [
            'brand_id'        => $this->brand_id,
            'name'            => $this->name,
            'start_date'      => $this->start_date,
            'end_date'        => $this->end_date,
            'platforms'       => $this->platforms       ?? ['linkedin', 'instagram'],
            'posts_per_week'  => $this->posts_per_week  ?? ['linkedin' => 3, 'instagram' => 4],
            'themes'          => $this->themes          ?? [],
            'brief'           => $this->brief instanceof Optional ? null : $this->brief,
            'reference_urls'  => $this->reference_urls  ?? [],
            'target_audience' => ($this->target_audience instanceof Optional) ? null : ($this->target_audience ?? null),
            'content_pillars' => $this->content_pillars ?? [],
            'competitors'     => $this->competitors      ?? [],
            'special_dates'   => $this->special_dates   ?? [],
            'custom_prompt'   => $this->custom_prompt instanceof Optional ? null : $this->custom_prompt,
            'editorial_preset' => $this->editorial_preset ?? EditorialPreset::Standard->value,
        ];
    }
}
