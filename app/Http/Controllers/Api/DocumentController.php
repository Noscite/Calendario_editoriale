<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Document\Jobs\ProcessDocumentJob;
use App\Domain\Document\Models\BrandDocument;
use App\Domain\Document\Models\DocumentChunk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


/**
 * Upload e gestione documenti brand — corrisponde a documents.py (Python).
 */
final class DocumentController extends Controller
{
    // POST /api/documents/upload/{brand_id}
    public function upload(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::where('id', $brandId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $request->validate([
            'file'        => 'required|file|max:20480', // 20MB
            'description' => 'nullable|string',
        ]);

        $file   = $request->file('file');
        $ext    = strtolower($file->getClientOriginalExtension());
        $allowed = ['pdf', 'docx', 'doc', 'pptx', 'ppt', 'txt', 'md'];

        if (! in_array($ext, $allowed)) {
            return response()->json([
                'detail' => "Tipo file non supportato. Formati accettati: " . implode(', ', $allowed),
            ], 400);
        }

        $uniqueFilename = Str::uuid() . '_' . $file->getClientOriginalName();
        $dir            = "documents/{$brandId}";
        $path           = $file->storeAs($dir, $uniqueFilename, 'local');

        $document = BrandDocument::create([
            'brand_id'            => $brandId,
            'filename'            => $uniqueFilename,
            'original_filename'   => $file->getClientOriginalName(),
            'file_type'           => $ext,
            'file_size'           => $file->getSize(),
            'file_path'           => Storage::disk('local')->path($path),
            'description'         => $request->input('description'),
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        ProcessDocumentJob::dispatch($document->id);

        return response()->json([
            'id'       => $document->id,
            'filename' => $document->original_filename,
            'status'   => 'uploaded',
            'message'  => 'Documento caricato. Elaborazione in corso...',
        ], 201);
    }

    // GET /api/documents/list/{brand_id}
    public function index(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::where('id', $brandId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $documents = BrandDocument::where('brand_id', $brandId)
            ->orderByDesc('uploaded_at')
            ->get();

        $result = $documents->map(function ($doc) {
            return [
                'id'                => $doc->id,
                'filename'          => $doc->filename,
                'original_filename' => $doc->original_filename,
                'file_type'         => $doc->file_type,
                'file_size'         => $doc->file_size,
                'extraction_status' => $doc->extraction_status,
                'analysis_status'   => $doc->analysis_status,
                'uploaded_at'       => $doc->uploaded_at?->toIso8601String(),
                'description'       => $doc->description,
                'summary'           => $doc->summary,
                'key_topics'        => $doc->key_topics,
                'chunks_count'      => $doc->chunks()->count(),
            ];
        });

        return response()->json($result);
    }

    // GET /api/documents/{id}
    public function show(int $id, Request $request): JsonResponse
    {
        $document = BrandDocument::with('brand')
            ->whereHas('brand', fn ($q) => $q->where('organization_id', $request->user()->organization_id))
            ->findOrFail($id);

        return response()->json([
            'id'                => $document->id,
            'original_filename' => $document->original_filename,
            'file_type'         => $document->file_type,
            'file_size'         => $document->file_size,
            'extraction_status' => $document->extraction_status,
            'analysis_status'   => $document->analysis_status,
            'uploaded_at'       => $document->uploaded_at?->toIso8601String(),
            'description'       => $document->description,
            'summary'           => $document->summary,
            'key_topics'        => $document->key_topics,
            'chunks_count'      => $document->chunks()->count(),
        ]);
    }

    // DELETE /api/documents/{id}
    public function destroy(int $id, Request $request): JsonResponse
    {
        $document = BrandDocument::whereHas('brand', fn ($q) =>
            $q->where('organization_id', $request->user()->organization_id)
        )->findOrFail($id);

        // Elimina file fisico
        if ($document->file_path && file_exists($document->file_path)) {
            @unlink($document->file_path);
        }

        $document->delete();

        return response()->json(['message' => 'Documento eliminato']);
    }

    // POST /api/documents/search/{brand_id}
    public function search(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::where('id', $brandId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $data = $request->validate([
            'query' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = $data['limit'] ?? 5;

        // TODO: Semantic search tramite RAG service (embeddings)
        // Per ora restituisce ricerca full-text semplice
        $chunks = DocumentChunk::where('brand_id', $brandId)
            ->where('content', 'ilike', "%{$data['query']}%")
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'content'    => $c->content,
                'filename'   => $c->document?->original_filename ?? '',
                'similarity' => 0.0,
            ]);

        return response()->json([
            'query'   => $data['query'],
            'results' => $chunks,
        ]);
    }

    // POST /api/documents/reprocess/{id}
    public function reprocess(int $id, Request $request): JsonResponse
    {
        $document = BrandDocument::whereHas('brand', fn ($q) =>
            $q->where('organization_id', $request->user()->organization_id)
        )->findOrFail($id);

        // Reset status
        $document->extraction_status = 'pending';
        $document->analysis_status   = 'pending';
        $document->save();

        // Elimina vecchi chunks
        DocumentChunk::where('document_id', $id)->delete();

        ProcessDocumentJob::dispatch($document->id);

        return response()->json(['message' => 'Riprocessamento avviato']);
    }
}
