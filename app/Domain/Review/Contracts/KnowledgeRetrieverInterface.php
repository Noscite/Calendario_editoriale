<?php

declare(strict_types=1);

namespace App\Domain\Review\Contracts;

use App\Domain\Brand\Models\Brand;

interface KnowledgeRetrieverInterface
{
    /**
     * Recupera i top-K chunk più rilevanti dalla KB del brand.
     *
     * @return array<int, array{chunk_id: int, content: string, similarity: float, document_filename: string}>
     */
    public function retrieve(Brand $brand, string $query, int $topK = 3): array;
}
