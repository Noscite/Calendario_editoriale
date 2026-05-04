<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Review\Contracts\KnowledgeRetrieverInterface;

/**
 * Stub retriever per i test e per ambienti senza pgvector.
 *
 * Ritorna i chunk preimpostati via $stub. Non interroga il DB né
 * effettua chiamate di embedding.
 */
final class StubKnowledgeRetriever implements KnowledgeRetrieverInterface
{
    /**
     * @param  array<int, array{chunk_id: int, content: string, similarity: float, document_filename: string}>  $stub
     */
    public function __construct(
        private readonly array $stub = [],
    ) {
    }

    public function retrieve(Brand $brand, string $query, int $topK = 3): array
    {
        return array_slice($this->stub, 0, $topK);
    }
}
