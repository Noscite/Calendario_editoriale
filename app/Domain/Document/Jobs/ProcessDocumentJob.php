<?php

declare(strict_types=1);

namespace App\Domain\Document\Jobs;

use App\Domain\Document\Models\BrandDocument;
use App\Domain\Document\Models\DocumentChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProcessDocumentJob — elabora un documento brand caricato.
 *
 * Replica la logica di process_document() da rag_service.py:
 *  1. Estrae testo dal file (txt/md nativo; PDF/DOCX richiedono librerie esterne)
 *  2. Aggiorna extraction_status
 *  3. Analizza con Claude per summary, key_topics, document_type
 *  4. Chunka il testo e salva DocumentChunk
 *  5. Aggiorna analysis_status
 *
 * Nota: per PDF/DOCX installare smalot/pdfparser o spatie/pdf-to-text.
 */
final class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(
        private readonly int $documentId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $document = BrandDocument::find($this->documentId);
        if (! $document) {
            Log::info("[DOC] Document {$this->documentId} not found");
            return;
        }

        Log::info("[DOC] Processing document {$this->documentId}: {$document->original_filename}");

        // ── 1. Estrazione testo ──
        $document->extraction_status = 'processing';
        $document->save();

        $text = $this->extractText($document);

        if ($text === null) {
            $document->extraction_status = 'failed';
            $document->save();
            Log::warning("[DOC] Text extraction failed for {$document->original_filename}");
            return;
        }

        $document->extraction_status = 'completed';
        $document->save();

        Log::info("[DOC] Extracted " . strlen($text) . " chars from {$document->original_filename}");

        // ── 2. Analisi con Claude ──
        $document->analysis_status = 'processing';
        $document->save();

        try {
            $analysis = $this->analyzeWithClaude($text, $document->original_filename);
            $document->summary    = $analysis['summary'] ?? null;
            $document->key_topics = $analysis['key_topics'] ?? [];
            $document->analysis_status = 'completed';
            $document->save();

            Log::info("[DOC] Analysis completed for {$document->original_filename}");
        } catch (\Throwable $e) {
            Log::warning("[DOC] Claude analysis failed: {$e->getMessage()}");
            $document->analysis_status = 'failed';
            $document->save();
        }

        // ── 3. Chunking e salvataggio ──
        DocumentChunk::where('document_id', $this->documentId)->delete();

        $chunks = $this->chunkText($text);
        foreach ($chunks as $i => $chunk) {
            DocumentChunk::create([
                'document_id' => $this->documentId,
                'brand_id'    => $document->brand_id,
                'content'     => $chunk,
                'chunk_index' => $i,
            ]);
        }

        Log::info("[DOC] ✅ Saved " . count($chunks) . " chunks for document {$this->documentId}");
    }

    public function failed(?\Throwable $exception): void
    {
        if ($exception && function_exists('\Sentry\captureException')) {
            \Sentry\captureException($exception);
        }

        Log::error("[DOC] ❌ Processing failed for {$this->documentId}: " . ($exception?->getMessage() ?? 'unknown'));

        try {
            $document = BrandDocument::find($this->documentId);
            if ($document) {
                $document->extraction_status = 'failed';
                $document->analysis_status   = 'failed';
                $document->save();
            }
        } catch (\Throwable) {
            // silenzioso
        }
    }

    // ══════════════════════════════════════════════════════════
    //  Private helpers
    // ══════════════════════════════════════════════════════════

    /**
     * Estrae il testo dal documento. Supporta txt/md nativamente.
     * PDF/DOCX/PPT richiedono librerie esterne — restituisce null se non supportato.
     */
    private function extractText(BrandDocument $document): ?string
    {
        $path = $document->file_path;
        if (! $path || ! file_exists($path)) {
            Log::warning("[DOC] File not found: {$path}");
            return null;
        }

        $ext = strtolower($document->file_type);

        return match ($ext) {
            'txt', 'md' => file_get_contents($path) ?: null,
            'pdf'       => $this->extractFromPdf($path),
            'docx', 'doc' => $this->extractFromDocx($path),
            default     => null,
        };
    }

    private function extractFromPdf(string $path): ?string
    {
        // Prova con pdftotext (poppler-utils, se disponibile sul sistema)
        $output = [];
        $returnCode = 0;
        exec('pdftotext ' . escapeshellarg($path) . ' - 2>/dev/null', $output, $returnCode);

        if ($returnCode === 0 && ! empty($output)) {
            return implode("\n", $output);
        }

        Log::info("[DOC] pdftotext not available or failed for {$path}. Install poppler-utils.");
        return null;
    }

    private function extractFromDocx(string $path): ?string
    {
        // Tenta di leggere il contenuto XML del docx (formato ZIP)
        try {
            if (! class_exists(\ZipArchive::class)) {
                return null;
            }

            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return null;
            }

            $content = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($content === false) {
                return null;
            }

            // Rimuovi tag XML e decodifica entità
            $text = strip_tags($content);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', $text);

            return trim($text) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Analizza il documento con Claude per estrarre summary e key_topics.
     * Replica di analyze_document() da rag_service.py.
     */
    private function analyzeWithClaude(string $text, string $filename): array
    {
        $apiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));
        if (! $apiKey) {
            return [];
        }

        $textExcerpt = mb_substr($text, 0, 3000);

        $prompt = <<<PROMPT
Analizza questo documento aziendale e fornisci:
1. Un breve sommario (2-3 frasi)
2. I 5 principali argomenti trattati

## DOCUMENTO: {$filename}
{$textExcerpt}

## OUTPUT (JSON valido)
{"document_type": "...", "summary": "...", "key_topics": ["...", "...", "...", "...", "..."], "language": "italiano"}

Rispondi SOLO con il JSON.
PROMPT;

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-20250514',
                'max_tokens' => 500,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Claude API error: HTTP {$response->status()}");
        }

        $content = $response->json('content.0.text', '');
        $content = trim($content);

        // Rimuovi markdown se presente
        if (str_starts_with($content, '```')) {
            $parts   = explode('```', $content);
            $content = $parts[1] ?? $content;
            if (str_starts_with($content, 'json')) {
                $content = substr($content, 4);
            }
        }

        return json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Chunka il testo in segmenti da ~500 parole con overlap.
     * Replica di chunk_text() da rag_service.py.
     */
    private function chunkText(string $text, int $chunkSize = 500, int $overlap = 50): array
    {
        $words  = preg_split('/\s+/', trim($text));
        $chunks = [];
        $i      = 0;

        while ($i < count($words)) {
            $chunk    = array_slice($words, $i, $chunkSize);
            $chunks[] = implode(' ', $chunk);
            $i       += ($chunkSize - $overlap);
        }

        return array_filter($chunks, fn ($c) => strlen(trim($c)) > 50);
    }
}
