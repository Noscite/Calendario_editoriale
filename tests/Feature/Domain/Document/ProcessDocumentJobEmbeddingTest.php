<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Document\Jobs\ProcessDocumentJob;
use App\Domain\Document\Models\BrandDocument;
use App\Domain\Document\Models\DocumentChunk;
use App\Domain\Document\Services\OpenAiEmbeddingClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fake che bypassa OpenAI: ogni chiamata è registrata in $calls.
 * Il comportamento è iniettabile via callback $onEmbed.
 */
function makeFakeEmbedder(?Closure $onEmbed = null): object
{
    return new class($onEmbed) extends OpenAiEmbeddingClient
    {
        public array $calls = [];

        public function __construct(public ?Closure $onEmbed = null)
        {
            parent::__construct();
        }

        public function withBrand(?Brand $brand): static
        {
            return $this;
        }

        public function embed(array $texts, string $model = 'text-embedding-3-small'): array
        {
            $this->calls[] = $texts;

            if ($this->onEmbed !== null) {
                return ($this->onEmbed)($texts, count($this->calls));
            }

            $vec = array_fill(0, 1536, 0.0);
            return array_fill(0, count($texts), $vec);
        }
    };
}

/**
 * Recupera la stringa pgvector di un chunk (NULL se non popolato).
 */
function chunkEmbeddingText(int $chunkId): ?string
{
    $row = DB::selectOne(
        'SELECT embedding::text AS embedding FROM document_chunks WHERE id = ?',
        [$chunkId]
    );

    return $row?->embedding;
}

/**
 * Crea un BrandDocument con un file txt reale su disco.
 */
function makeProcessableDocument(int $approxWords = 100): BrandDocument
{
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org);

    $tmpPath = sys_get_temp_dir() . '/kalendarium_test_' . uniqid() . '.txt';
    $text    = trim(str_repeat('lorem ipsum dolor sit amet consectetur adipiscing ', (int) ceil($approxWords / 6)));
    file_put_contents($tmpPath, $text);

    return BrandDocument::create([
        'brand_id'          => $brand->id,
        'filename'          => basename($tmpPath),
        'original_filename' => 'test.txt',
        'file_type'         => 'txt',
        'file_size'         => filesize($tmpPath) ?: 0,
        'file_path'         => $tmpPath,
        'extraction_status' => 'pending',
        'analysis_status'   => 'pending',
    ]);
}

beforeEach(function () {
    // Disabilita Claude per non chiamare HTTP esterno durante il job
    config()->set('services.anthropic.api_key', '');
    config()->set('services.openai.api_key', 'sk-test');
});

it('generates embeddings for all chunks', function () {
    $doc  = makeProcessableDocument(2000);
    $fake = makeFakeEmbedder();
    app()->instance(OpenAiEmbeddingClient::class, $fake);

    Http::preventStrayRequests();

    ProcessDocumentJob::dispatchSync($doc->id);

    $chunks = DocumentChunk::where('document_id', $doc->id)->get();
    expect($chunks->count())->toBeGreaterThan(0);

    foreach ($chunks as $chunk) {
        expect(chunkEmbeddingText((int) $chunk->id))->not->toBeNull();
    }

    expect($fake->calls)->not->toBeEmpty();

    @unlink($doc->file_path);
});

it('continues if one chunk fails to embed (partial batch result)', function () {
    $doc = makeProcessableDocument(800);

    // Crea manualmente 2 chunk per testare in isolamento (bypass full pipeline)
    $chunkA = DocumentChunk::create([
        'document_id' => $doc->id,
        'brand_id'    => $doc->brand_id,
        'content'     => 'primo chunk',
        'chunk_index' => 0,
    ]);
    $chunkB = DocumentChunk::create([
        'document_id' => $doc->id,
        'brand_id'    => $doc->brand_id,
        'content'     => 'secondo chunk',
        'chunk_index' => 1,
    ]);

    // L'embedder ritorna un vettore valido per chunk 0 e null per chunk 1
    $fake = makeFakeEmbedder(function (array $texts) {
        $valid = array_fill(0, 1536, 0.5);
        return [$valid, null];
    });

    $job = new ProcessDocumentJob($doc->id);
    $job->generateEmbeddings($doc->fresh(), $fake);

    expect(chunkEmbeddingText($chunkA->id))->not->toBeNull();
    expect(chunkEmbeddingText($chunkB->id))->toBeNull();

    @unlink($doc->file_path);
});

it('skips chunks with existing embedding', function () {
    $doc = makeProcessableDocument(800);

    // Chunk con embedding pre-esistente
    $existing = DocumentChunk::create([
        'document_id' => $doc->id,
        'brand_id'    => $doc->brand_id,
        'content'     => 'gia embeddato',
        'chunk_index' => 0,
    ]);
    $preset       = '[' . implode(',', array_fill(0, 1536, 0.99)) . ']';
    DB::update(
        'UPDATE document_chunks SET embedding = ?::vector WHERE id = ?',
        [$preset, $existing->id]
    );

    // Chunk vuoto, da embeddare
    $fresh = DocumentChunk::create([
        'document_id' => $doc->id,
        'brand_id'    => $doc->brand_id,
        'content'     => 'da embeddare',
        'chunk_index' => 1,
    ]);

    $fake = makeFakeEmbedder();
    $job  = new ProcessDocumentJob($doc->id);
    $job->generateEmbeddings($doc->fresh(), $fake);

    // Il fake deve aver ricevuto SOLO il chunk fresh (1 testo)
    expect($fake->calls)->toHaveCount(1);
    expect($fake->calls[0])->toBe(['da embeddare']);

    // Embedding pre-esistente preservato
    expect(chunkEmbeddingText($existing->id))->toContain('0.99');
    // Chunk fresh ora ha embedding
    expect(chunkEmbeddingText($fresh->id))->not->toBeNull();

    @unlink($doc->file_path);
});
