<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UpdateCampaignData extends Data
{
    public function __construct(
        #[Max(255)]
        public readonly Optional|string $name,
        #[Max(500)]
        public readonly Optional|string|null $description,
        public readonly Optional|string|null $brief,
        public readonly Optional|string|null $start_date,
        public readonly Optional|string|null $end_date,
        #[In(['draft', 'planning', 'active', 'completed', 'archived'])]
        public readonly Optional|string $status,
        /** @var int[] */
        public readonly Optional|array|null $brand_ids,
        /**
         * Documenti KB del brand selezionati per la campagna.
         * Nullable (default null) per non rompere payload che non includono il
         * campo (pattern fix wizard 422): assente => selezione invariata.
         *
         * @var array<int, array{id:int, inject_mode?:string}>|null
         */
        public readonly ?array $brand_documents = null,
    ) {}
}
