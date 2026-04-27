<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use App\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Excel del calendario — corrisponde a export.py (Python).
 */
final class ExportController extends Controller
{
    // GET /api/export/excel/{project_id}
    public function excel(int $projectId, Request $request): StreamedResponse
    {
        $project = Project::whereHas('brand', fn ($q) =>
            $q->where('organization_id', $request->user()->organization_id)
        )->findOrFail($projectId);

        $brand = Brand::find($project->brand_id);
        $posts = Post::where('project_id', $projectId)
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        try {
            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Piano Editoriale');

            $sheet->setCellValue('A1', "Piano Editoriale: {$project->name}");
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->setCellValue('A2', 'Brand: ' . ($brand?->name ?? 'N/A'));
            $sheet->setCellValue('A3', "Periodo: {$project->start_date?->format('Y-m-d')} - {$project->end_date?->format('Y-m-d')}");
            $sheet->setCellValue('A4', 'Esportato il: ' . now()->format('d/m/Y H:i'));

            $headers = ['Data', 'Ora', 'Piattaforma', 'Contenuto', 'Hashtags', 'Pillar', 'Tipo', 'CTA', 'Visual', 'Status'];
            $row     = 6;

            foreach ($headers as $col => $header) {
                $cell  = $sheet->getCellByColumnAndRow($col + 1, $row);
                $cell->setValue($header);
                $style = $sheet->getStyleByColumnAndRow($col + 1, $row);
                $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3DAFA8');
                $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }

            $platformColors = [
                'linkedin'        => 'E3F2FD',
                'instagram'       => 'FCE4EC',
                'facebook'        => 'E8EAF6',
                'google_business' => 'E8F5E9',
            ];

            foreach ($posts as $post) {
                $row++;
                $values = [
                    $post->scheduled_date?->format('d/m/Y') ?? '',
                    $post->scheduled_time ?? '',
                    strtoupper($post->getRawOriginal('platform') ?? ''),
                    $post->content ?? '',
                    is_array($post->hashtags) ? implode(', ', $post->hashtags) : '',
                    $post->pillar ?? '',
                    $post->getRawOriginal('post_type') ?? '',
                    $post->cta ?? '',
                    $post->visual_suggestion ?? '',
                    $post->getRawOriginal('status') ?? 'draft',
                ];

                $platform  = $post->getRawOriginal('platform') ?? '';
                $fillColor = $platformColors[$platform] ?? 'FFFFFF';

                foreach ($values as $col => $value) {
                    $cell  = $sheet->getCellByColumnAndRow($col + 1, $row);
                    $cell->setValue($value);
                    $style = $sheet->getStyleByColumnAndRow($col + 1, $row);
                    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fillColor);

                    if ($col === 3) {
                        $style->getAlignment()->setWrapText(true);
                    }
                }
            }

            $widths = [12, 8, 12, 60, 30, 15, 12, 25, 30, 10];
            foreach ($widths as $i => $w) {
                $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
            }
        } catch (\Throwable $e) {
            report($e);
            throw new BusinessException(
                'Impossibile generare il file Excel. Riprova tra qualche istante.',
                'EXPORT_GENERATION_FAILED',
                500,
                $e,
            );
        }

        $filename = 'piano_editoriale_' . str_replace(' ', '_', $project->name) . '_' . now()->format('Ymd') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename={$filename}",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // GET /api/export/pdf/{project_id}
    public function pdf(int $projectId, Request $request): StreamedResponse
    {
        // Delegato all'export Excel finché non viene implementato il PDF nativo.
        return $this->excel($projectId, $request);
    }
}
