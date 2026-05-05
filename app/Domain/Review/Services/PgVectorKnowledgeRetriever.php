<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Document\Contracts\OpenAiEmbeddingClientInterface;
use App\Domain\Review\Contracts\KnowledgeRetrieverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retrieve dei chunk KB più rilevanti via cosine similarity (pgvector).
 *
 * Embedda la query con OpenAI text-embedding-3-small (1536 dim), poi ordina
 * i chunk del brand per distanza coseno con `embedding <=> ?::vector` e
 * applica una soglia minima di similarity (0.3) per evitare match irrilevanti.
 */
final class PgVectorKnowledgeRetriever implements KnowledgeRetrieverInterface
{
    private const MIN_SIMILARITY = 0.30;

    public function __construct(
        private readonly OpenAiEmbeddingClientInterface $embedder,
    ) {
    }

    public function retrieve(Brand $brand, string $query, int $topK = 3): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $embedder = $this->embedder->withBrand($brand);
            $vectors  = $embedder->embed([$query]);
        } catch (\Throwable $e) {
            Log::warning('[KB_RETRIEVE] Embedding query fallito', [
                'brand_id' => $brand->id,
                'error'    => $e->getMessage(),
            ]);
            return [];
        }

        $vector = $vectors[0] ?? null;
        if (! is_array($vector) || $vector === []) {
            return [];
        }

        $vectorString = '[' . implode(',', $vector) . ']';

        $rows = DB::select(
            <<<'SQL'
SELECT c.id AS chunk_id,
       c.content,
       d.original_filename,
       1 - (c.embedding <=> ?::vector) AS similarity
FROM document_chunks c
JOIN brand_documents d ON d.id = c.document_id
WHERE c.brand_id = ?
  AND c.embedding IS NOT NULL
ORDER BY c.embedding <=> ?::vector
LIMIT ?
SQL,
            [$vectorString, $brand->id, $vectorString, $topK],
        );

        $out = [];
        foreach ($rows as $row) {
            $similarity = (float) $row->similarity;
            if ($similarity < self::MIN_SIMILARITY) {
                continue;
            }
            $out[] = [
                'chunk_id'          => (int) $row->chunk_id,
                'content'           => (string) $row->content,
                'similarity'        => $similarity,
                'document_filename' => (string) $row->original_filename,
            ];
        }

        return $out;
    }
}
