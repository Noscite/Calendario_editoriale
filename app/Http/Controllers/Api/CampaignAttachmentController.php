<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Campaign\Jobs\ExtractAttachmentTextJob;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CampaignAttachmentController extends Controller
{
    public const MAX_ATTACHMENTS_PER_CAMPAIGN = 5;
    public const MAX_SIZE_BYTES               = 25 * 1024 * 1024;

    public function index(int $campaignId, Request $request): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);

        return response()->json([
            'data' => $campaign->attachments()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (CampaignAttachment $a) => $this->serialize($a))
                ->all(),
        ]);
    }

    public function store(int $campaignId, Request $request): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);

        $request->validate([
            'file' => 'required|file|max:' . (self::MAX_SIZE_BYTES / 1024),
        ]);

        if ($campaign->attachments()->count() >= self::MAX_ATTACHMENTS_PER_CAMPAIGN) {
            throw ValidationException::withMessages([
                'file' => 'Massimo ' . self::MAX_ATTACHMENTS_PER_CAMPAIGN
                    . ' allegati per campagna. Elimina un allegato esistente prima di caricarne un altro.',
            ]);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $extension      = $file->getClientOriginalExtension();
        $storedFilename = Str::uuid()->toString() . ($extension ? ".{$extension}" : '');
        $storageDir     = storage_path("app/campaign-attachments/{$campaign->id}");
        if (! is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        $file->move($storageDir, $storedFilename);

        $attachment = CampaignAttachment::create([
            'campaign_id'         => $campaign->id,
            'uploaded_by_user_id' => $request->user()?->id,
            'original_filename'   => $file->getClientOriginalName(),
            'stored_filename'     => $storedFilename,
            'mime_type'           => $file->getClientMimeType(),
            'size_bytes'          => filesize("{$storageDir}/{$storedFilename}") ?: 0,
            'extraction_status'   => CampaignAttachment::STATUS_PENDING,
        ]);

        ExtractAttachmentTextJob::dispatch($attachment->id);

        return response()->json(array_merge($this->serialize($attachment), [
            'message' => 'File caricato. Estrazione testo in corso (potrebbe richiedere qualche secondo).',
        ]), 201);
    }

    public function destroy(int $campaignId, int $attachmentId): JsonResponse
    {
        $campaign   = Campaign::findOrFail($campaignId);
        $attachment = CampaignAttachment::where('id', $attachmentId)
            ->where('campaign_id', $campaign->id)
            ->firstOrFail();

        $path = $attachment->getStoragePath();
        if (file_exists($path)) {
            @unlink($path);
        }

        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Allegato eliminato']);
    }

    public function download(int $attachmentId): BinaryFileResponse
    {
        $attachment = CampaignAttachment::findOrFail($attachmentId);

        // Authorization: il global scope su Campaign limita l'accesso all'org dell'utente.
        // Se l'utente non può accedere alla campaign del attachment, il find sopra non è
        // sufficiente — verifichiamo esplicitamente che la campaign esista nello scope.
        $campaign = Campaign::find($attachment->campaign_id);
        abort_unless($campaign !== null, 404);

        $path = $attachment->getStoragePath();
        abort_unless(file_exists($path), 404);

        return response()->download($path, $attachment->original_filename);
    }

    /**
     * @return array{
     *   id: int, original_filename: string, mime_type: string,
     *   size_bytes: int, size_human: string,
     *   extraction_status: string, extraction_error: ?string,
     *   has_text: bool, text_length: int,
     *   uploaded_at: ?string, download_url: string,
     * }
     */
    private function serialize(CampaignAttachment $a): array
    {
        return [
            'id'                => $a->id,
            'original_filename' => $a->original_filename,
            'mime_type'         => $a->mime_type,
            'size_bytes'        => $a->size_bytes,
            'size_human'        => $this->humanFilesize($a->size_bytes),
            'extraction_status' => $a->extraction_status,
            'extraction_error'  => $a->extraction_error,
            'has_text'          => ! empty($a->extracted_text),
            'text_length'       => $a->extracted_text ? mb_strlen($a->extracted_text) : 0,
            'uploaded_at'       => $a->created_at?->toIso8601String(),
            'download_url'      => $a->getDownloadUrl(),
        ];
    }

    private function humanFilesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;
        $size  = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}
