<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Services;

use App\Domain\Campaign\Models\CampaignAttachment;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Estrae il testo da un CampaignAttachment per uso come Knowledge Base
 * nella generazione AI dei post di una campagna.
 *
 * Formati supportati:
 *  - application/pdf                                   → smalot/pdfparser
 *  - application/vnd.openxmlformats-...wordprocessingml,
 *    application/msword                                 → phpoffice/phpword
 *  - application/vnd.openxmlformats-...spreadsheetml,
 *    application/vnd.ms-excel                           → phpoffice/phpspreadsheet
 *  - text/csv, text/plain, text/markdown, application/json → file_get_contents
 *  - text/html                                          → strip_tags
 *
 * Altri formati: status='unsupported' (no error, semplicemente non leggibili).
 */
final class AttachmentTextExtractor
{
    private const MAX_TEXT_LENGTH = 200_000;

    public function extract(CampaignAttachment $attachment): void
    {
        $attachment->update(['extraction_status' => CampaignAttachment::STATUS_PROCESSING]);

        $path = $attachment->getStoragePath();

        if (! file_exists($path)) {
            $attachment->update([
                'extraction_status' => CampaignAttachment::STATUS_FAILED,
                'extraction_error'  => "File non trovato: {$path}",
            ]);

            return;
        }

        try {
            $text = $this->extractByMime($attachment->mime_type, $path);

            if ($text === null) {
                $attachment->update([
                    'extraction_status' => CampaignAttachment::STATUS_UNSUPPORTED,
                    'extraction_error'  => "MIME type '{$attachment->mime_type}' non supportato per estrazione testo",
                ]);

                return;
            }

            $text = trim($text);
            if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH) . "\n\n[...troncato per dimensione eccessiva]";
            }

            $attachment->update([
                'extracted_text'    => $text,
                'extraction_status' => CampaignAttachment::STATUS_COMPLETED,
                'extracted_at'      => now(),
                'extraction_error'  => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[AttachmentTextExtractor] Failed', [
                'attachment_id' => $attachment->id,
                'mime'          => $attachment->mime_type,
                'error'         => $e->getMessage(),
            ]);

            $attachment->update([
                'extraction_status' => CampaignAttachment::STATUS_FAILED,
                'extraction_error'  => $e->getMessage(),
            ]);
        }
    }

    private function extractByMime(string $mime, string $path): ?string
    {
        return match (true) {
            str_contains($mime, 'pdf')
                => $this->extractPdf($path),

            str_contains($mime, 'wordprocessingml.document') || $mime === 'application/msword'
                => $this->extractDocx($path),

            str_contains($mime, 'spreadsheetml.sheet') || $mime === 'application/vnd.ms-excel'
                => $this->extractSpreadsheet($path),

            in_array($mime, ['text/plain', 'text/markdown', 'text/csv', 'application/json'], true)
                => $this->extractTextFile($path),

            $mime === 'text/html'
                => $this->extractHtml($path),

            default => null,
        };
    }

    private function extractPdf(string $path): string
    {
        $parser   = new PdfParser();
        $document = $parser->parseFile($path);

        return $document->getText() ?? '';
    }

    private function extractDocx(string $path): string
    {
        $phpWord = PhpWordIOFactory::load($path);
        $text    = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $text .= $child->getText() . ' ';
                        }
                    }
                    $text .= "\n";
                }
            }
        }

        return $text;
    }

    private function extractSpreadsheet(string $path): string
    {
        $spreadsheet = SpreadsheetIOFactory::load($path);
        $text        = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $text .= "# Sheet: {$sheet->getTitle()}\n";
            foreach ($sheet->toArray(null, true, true, false) as $row) {
                $cleanRow = array_filter($row, fn ($v) => $v !== null && $v !== '');
                if (! empty($cleanRow)) {
                    $text .= implode(' | ', $cleanRow) . "\n";
                }
            }
            $text .= "\n";
        }

        return $text;
    }

    private function extractTextFile(string $path): string
    {
        return file_get_contents($path) ?: '';
    }

    private function extractHtml(string $path): string
    {
        $html = file_get_contents($path) ?: '';

        return trim(strip_tags($html));
    }
}
