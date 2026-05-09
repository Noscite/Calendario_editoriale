<?php

declare(strict_types=1);

namespace App\Domain\Territorial\DTOs;

use Carbon\Carbon;

final readonly class EventPayload
{
    public function __construct(
        public string $externalId,
        public string $title,
        public ?string $abstract,
        public ?string $description,
        public array $categories,
        public ?string $venueName,
        public ?string $city,
        public ?string $province,
        public ?float $lat,
        public ?float $lng,
        public ?Carbon $startAt,
        public ?Carbon $endAt,
        public ?string $externalUrl,
        public ?string $imageUrl,
        public array $raw,
    ) {}
}
