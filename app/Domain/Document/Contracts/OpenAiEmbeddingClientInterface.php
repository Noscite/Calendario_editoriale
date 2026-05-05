<?php

declare(strict_types=1);

namespace App\Domain\Document\Contracts;

use App\Domain\Brand\Models\Brand;

interface OpenAiEmbeddingClientInterface
{
    /**
     * Ritorna una nuova istanza configurata con la chiave del brand
     * (con fallback al system tenant gestito da BrandApiKeyService).
     */
    public function withBrand(?Brand $brand): self;

    /**
     * Genera embedding per un array di stringhe.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts, string $model = 'text-embedding-3-small'): array;
}
