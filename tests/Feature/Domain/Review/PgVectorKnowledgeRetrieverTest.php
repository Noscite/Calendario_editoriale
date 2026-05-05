<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Brand\Services\BrandApiKeyService;
use App\Domain\Document\Contracts\OpenAiEmbeddingClientInterface;
use App\Domain\Document\Models\BrandDocument;
use App\Domain\Document\Models\DocumentChunk;
use App\Domain\Review\Services\PgVectorKnowledgeRetriever;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.openai.api_key', 'sk-fake-test');
    Queue::fake();
});

/**
 * Embedder fake che mappa input text → un vettore controllato.
 * Permette di simulare vicinanza coseno deterministica.
 */
function makeRetrieverEmbedder(callable $vectorFor): OpenAiEmbeddingClientInterface
{
    return new class($vectorFor) implements OpenAiEmbeddingClientInterface
    {
        /** @var \Closure */
        private $resolver;

        public function __construct(callable $resolver)
        {
            $this->resolver = \Closure::fromCallable($resolver);
        }

        public function withBrand(?Brand $brand): self
        {
            return $this;
        }

        public function embed(array $texts, string $model = 'text-embedding-3-small'): array
        {
            return array_map(fn (string $t): array => ($this->resolver)($t), $texts);
        }
    };
}

function makeBrandWithKb(): Brand
{
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org);
    BrandApiKey::create([
        'brand_id'        => $brand->id,
        'key_name'        => BrandApiKeyService::OPENAI_API_KEY,
        'encrypted_value' => 'sk-fake-brand',
    ]);
    return $brand;
}

function makeChunkWithEmbedding(Brand $brand, string $content, array $vector, string $filename = 'doc.pdf'): DocumentChunk
{
    $doc = BrandDocument::create([
        'brand_id'          => $brand->id,
        'filename'          => $filename,
        'original_filename' => $filename,
        'file_type'         => 'pdf',
        'file_path'         => '/tmp/' . $filename,
        'extraction_status' => 'completed',
        'analysis_status'   => 'completed',
    ]);

    $chunk = DocumentChunk::create([
        'document_id' => $doc->id,
        'brand_id'    => $brand->id,
        'content'     => $content,
        'chunk_index' => 0,
    ]);

    $vectorString = '[' . implode(',', $vector) . ']';
    DB::update('UPDATE document_chunks SET embedding = ?::vector WHERE id = ?', [$vectorString, $chunk->id]);

    return $chunk;
}

function unitVector(int $dim, int $hotIndex): array
{
    $v             = array_fill(0, $dim, 0.0);
    $v[$hotIndex] = 1.0;
    return $v;
}

it('returns top-k chunks ordered by cosine similarity', function () {
    $brand = makeBrandWithKb();

    // 3 chunk con vettori canonici. Query allinearà perfettamente uno di loro.
    makeChunkWithEmbedding($brand, 'CHUNK_A', unitVector(1536, 0), 'a.pdf');
    makeChunkWithEmbedding($brand, 'CHUNK_B', unitVector(1536, 1), 'b.pdf');
    makeChunkWithEmbedding($brand, 'CHUNK_C', unitVector(1536, 2), 'c.pdf');

    // L'embedder ritorna unitVector(0) per qualsiasi query
    $retriever = new PgVectorKnowledgeRetriever(makeRetrieverEmbedder(fn ($t) => unitVector(1536, 0)));

    $results = $retriever->retrieve($brand, 'qualunque query', 3);

    // Solo il chunk A passa la soglia 0.30 di similarità (gli altri hanno cos sim 0)
    expect($results)->toHaveCount(1);
    expect($results[0]['content'])->toBe('CHUNK_A');
    expect($results[0]['similarity'])->toBeGreaterThan(0.99);
    expect($results[0]['document_filename'])->toBe('a.pdf');
});

it('filters chunks below threshold', function () {
    $brand = makeBrandWithKb();
    makeChunkWithEmbedding($brand, 'CHUNK_OFFAXIS', unitVector(1536, 100));

    // Query allineata a indice 0 → cos sim ~0 col chunk a indice 100
    $retriever = new PgVectorKnowledgeRetriever(makeRetrieverEmbedder(fn ($t) => unitVector(1536, 0)));

    expect($retriever->retrieve($brand, 'query', 3))->toBe([]);
});

it('returns empty when no chunks for brand', function () {
    $brand = makeBrandWithKb();

    $retriever = new PgVectorKnowledgeRetriever(makeRetrieverEmbedder(fn ($t) => unitVector(1536, 0)));
    expect($retriever->retrieve($brand, 'query', 3))->toBe([]);
});
