<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Jobs\RunBrandAuditJob;
use App\Domain\Audit\Models\BrandAudit;
use App\Domain\Brand\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

final class AuditController extends Controller
{
    /**
     * Avvia un nuovo audit per un brand.
     * POST /api/brands/{id}/audit
     */
    public function start(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::where('id', $brandId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $websiteUrl = $brand->website_url ?: $brand->website;

        if (! $websiteUrl) {
            return response()->json([
                'error' => 'Nessun sito web configurato per questo brand. Aggiorna il profilo brand con il tuo URL.',
            ], 422);
        }

        // Evita audit duplicati in corso (pending o running)
        $running = BrandAudit::where('brand_id', $brandId)
            ->whereIn('status', ['pending', 'running'])
            ->exists();

        if ($running) {
            return response()->json(['error' => 'Audit già in corso per questo brand.'], 409);
        }

        // Settore confermato dall'utente tramite modal di conferma (opzionale)
        $confirmedSector    = $request->input('sector');
        $confirmedSectorKey = $request->input('sector_key');

        $audit = BrandAudit::create([
            'brand_id'    => $brandId,
            'website_url' => $websiteUrl,
            'status'      => 'pending',
            // Salva settore confermato in standalone_data per uso nel job
            'standalone_data' => $confirmedSector ? [
                'sector'     => $confirmedSector,
                'sector_key' => $confirmedSectorKey,
            ] : null,
        ]);

        RunBrandAuditJob::dispatch($brandId, $audit->id)->onQueue('audits');

        return response()->json([
            'audit_id' => $audit->id,
            'status'   => 'pending',
            'message'  => 'Audit avviato. Tempo stimato: 2-3 minuti.',
        ], 202);
    }

    /**
     * Rileva automaticamente il settore di un URL.
     * POST /api/admin/audit-detect-sector
     *
     * Body: { "url": "https://example.com" }
     */
    public function detectSector(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'superuser') {
            return response()->json(['error' => 'Accesso non autorizzato.'], 403);
        }

        $validated = $request->validate(['url' => 'required|url|max:500']);

        $detector = app(\App\Domain\Audit\Services\SectorDetectorService::class);
        $result   = $detector->detect($validated['url']);

        return response()->json($result);
    }

    /**
     * Rileva settore per brand esistente.
     * POST /api/brands/{brandId}/audit-detect-sector
     */
    public function detectSectorForBrand(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::where('id', $brandId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $url = $brand->website_url ?: $brand->website;

        if (! $url) {
            return response()->json(['error' => 'Nessun sito web configurato.'], 422);
        }

        $detector = app(\App\Domain\Audit\Services\SectorDetectorService::class);
        $result   = $detector->detect($url);

        // Arricchisci con il settore già configurato nel brand
        $result['brand_sector']     = $brand->sector;
        $result['brand_sector_key'] = $brand->sector
            ? $detector->mapToKey($brand->sector)
            : null;

        return response()->json($result);
    }

    /**
     * Stato e risultato di un audit.
     * GET /api/audits/{auditId}
     */
    public function show(int $auditId, Request $request): JsonResponse
    {
        $audit = BrandAudit::with('brand')
            ->findOrFail($auditId);

        // Sicurezza: verifica org
        if ($audit->brand->organization_id !== $request->user()->organization_id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($this->formatAudit($audit));
    }

    /**
     * Ultimo audit di un brand.
     * GET /api/brands/{id}/audit/latest
     */
    public function latest(int $brandId, Request $request): JsonResponse
    {
        Brand::where('id', $brandId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $audit = BrandAudit::where('brand_id', $brandId)
            ->latest()
            ->first();

        if (! $audit) {
            return response()->json(['status' => 'none'], 200);
        }

        return response()->json($this->formatAudit($audit));
    }

    /**
     * Lista audit di un brand.
     * GET /api/brands/{id}/audits
     */
    public function history(int $brandId, Request $request): JsonResponse
    {
        Brand::where('id', $brandId)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $audits = BrandAudit::where('brand_id', $brandId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'id'            => $a->id,
                'status'        => $a->status,
                'score_overall' => $a->score_overall,
                'score_label'   => $a->score_label,
                'score_color'   => $a->score_color,
                'created_at'    => $a->created_at?->toIso8601String(),
                'completed_at'  => $a->completed_at?->toIso8601String(),
            ]);

        return response()->json($audits);
    }

    /**
     * Lista tutti gli audit (brand + standalone) — solo superuser.
     * GET /api/admin/audits
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'superuser') {
            return response()->json(['error' => 'Accesso non autorizzato.'], 403);
        }

        $type = $request->query('type', 'all'); // 'all' | 'brand' | 'standalone'

        $query = BrandAudit::with(['brand' => fn($q) => $q->with(['organization' => fn($q2) => $q2->select('id', 'name')])])
            ->orderByDesc('created_at');

        if ($type === 'brand') {
            $query->whereNotNull('brand_id');
        } elseif ($type === 'standalone') {
            $query->whereNull('brand_id');
        }

        $audits = $query->paginate(30);

        return response()->json([
            'data' => $audits->map(fn($a) => $this->formatAuditSummary($a)),
            'meta' => [
                'total'        => $audits->total(),
                'current_page' => $audits->currentPage(),
                'last_page'    => $audits->lastPage(),
            ],
        ]);
    }

    /**
     * Avvia audit standalone su URL libero — solo superuser.
     * POST /api/admin/audit-url
     *
     * Body JSON:
     * { "url": "https://example.com", "company_name": "...", "sector": "...", "notes": "..." }
     */
    public function startStandalone(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'superuser') {
            return response()->json(['error' => 'Accesso non autorizzato.'], 403);
        }

        $validated = $request->validate([
            'url'          => 'required|url|max:500',
            'company_name' => 'nullable|string|max:200',
            'sector'       => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $url = rtrim($validated['url'], '/');
        if (! str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        // Evita audit duplicati in corso sullo stesso URL
        $running = BrandAudit::whereNull('brand_id')
            ->where('website_url', $url)
            ->whereIn('status', ['pending', 'running'])
            ->exists();

        if ($running) {
            return response()->json(['error' => 'Audit già in corso per questo URL.'], 409);
        }

        $audit = BrandAudit::create([
            'brand_id'        => null,
            'website_url'     => $url,
            'status'          => 'pending',
            'standalone_data' => [
                'url'          => $url,
                'company_name' => $validated['company_name'] ?? null,
                'sector'       => $validated['sector']       ?? null,
                'notes'        => $validated['notes']        ?? null,
                'created_by'   => $request->user()->id,
            ],
            // Popola anche i campi legacy per compatibilità con il job
            'prospect_name'   => $validated['company_name'] ?? null,
            'prospect_sector' => $validated['sector']       ?? null,
        ]);

        RunBrandAuditJob::dispatch(null, $audit->id)->onQueue('audits');

        return response()->json([
            'audit_id' => $audit->id,
            'status'   => 'pending',
            'message'  => 'Audit avviato. Tempo stimato: 3-4 minuti.',
        ], 202);
    }

    /**
     * Lista audit standalone — solo superuser.
     * GET /api/admin/audits-standalone
     */
    public function standaloneIndex(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'superuser') {
            return response()->json(['error' => 'Accesso non autorizzato.'], 403);
        }

        $audits = BrandAudit::whereNull('brand_id')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $audits->map(fn($a) => $this->formatAuditSummary($a)),
            'meta' => [
                'total'        => $audits->total(),
                'current_page' => $audits->currentPage(),
                'last_page'    => $audits->lastPage(),
            ],
        ]);
    }

    /**
     * Genera share link per un audit.
     * POST /api/audits/{auditId}/share
     * POST /api/admin/audit-url/{auditId}/share
     */
    public function generateShareLink(int $auditId, Request $request): JsonResponse
    {
        $audit = BrandAudit::findOrFail($auditId);

        $isSuperuser = $request->user()->role === 'superuser';
        $isOwner     = $audit->brand_id &&
            $audit->brand?->organization_id === $request->user()->organization_id;

        if (! $isSuperuser && ! $isOwner) {
            return response()->json(['error' => 'Non autorizzato.'], 403);
        }

        if (! $audit->isCompleted()) {
            return response()->json(['error' => 'Audit non ancora completato.'], 422);
        }

        $shareUrl = $audit->getShareUrl();

        return response()->json([
            'share_url' => $shareUrl,
            'token'     => $audit->share_token,
            'is_public' => $audit->isStandalone(),
        ]);
    }

    /**
     * Download PDF per audit brand — utente autenticato proprietario.
     * GET /api/audits/{auditId}/pdf
     */
    public function downloadPdf(int $auditId, Request $request): \Illuminate\Http\Response
    {
        $audit = BrandAudit::with('brand')->findOrFail($auditId);

        $isSuperuser = $request->user()->role === 'superuser';
        $isOwner     = $audit->brand_id &&
            $audit->brand?->organization_id === $request->user()->organization_id;
        $isStandalone = $audit->isStandalone();

        if (! $isSuperuser && ! $isOwner && ! $isStandalone) {
            abort(403);
        }

        if (! $audit->isCompleted()) {
            abort(422, 'Audit non ancora completato.');
        }

        return $this->buildPdfResponse($audit);
    }

    /**
     * Download PDF audit standalone — solo superuser.
     * GET /api/admin/audit-url/{auditId}/pdf
     */
    public function standaloneDownloadPdf(int $auditId, Request $request): \Illuminate\Http\Response
    {
        if ($request->user()->role !== 'superuser') {
            abort(403);
        }

        $audit = BrandAudit::whereNull('brand_id')->findOrFail($auditId);

        if (! $audit->isCompleted()) {
            abort(422, 'Audit non ancora completato.');
        }

        return $this->buildPdfResponse($audit);
    }

    private function buildPdfResponse(BrandAudit $audit): \Illuminate\Http\Response
    {
        $analyzerResults = $audit->analyzer_results ?? [];
        $allIssues = array_merge(
            array_map(fn($i) => array_merge($i, ['category' => 'GDPR']),
                $analyzerResults['gdpr']['issues'] ?? []),
            array_map(fn($i) => array_merge($i, ['category' => 'SEO/GEO']),
                $analyzerResults['seo_geo']['issues'] ?? []),
            array_map(fn($i) => array_merge($i, ['category' => 'Accessibilità']),
                $analyzerResults['accessibility']['issues'] ?? []),
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.audit_report', [
            'brandName'        => $audit->display_name,
            'sector'           => $audit->display_sector ?: 'N/D',
            'websiteUrl'       => $audit->website_url,
            'auditId'          => $audit->id,
            'generatedAt'      => now()->format('d/m/Y H:i'),
            'scores'           => [
                'overall'          => $audit->score_overall,
                'neuromarketing'   => $audit->score_neuromarketing,
                'seo_geo'          => $audit->score_seo_geo,
                'gdpr'             => $audit->score_gdpr,
                'social_coherence' => $audit->score_social_coherence,
                'accessibility'    => $audit->score_accessibility,
                'performance'      => $audit->score_performance,
            ],
            'executiveSummary' => $audit->executive_summary,
            'criticalIssues'   => $audit->critical_issues ?? [],
            'allIssues'        => $allIssues,
            'recommendations'  => $audit->recommendations ?? [],
            'analyzerResults'  => $analyzerResults,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $slug     = Str::slug($audit->display_name ?: 'audit');
        $filename = "audit_{$slug}_" . now()->format('Ymd') . '.pdf';

        $response = $pdf->download($filename);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        return $response;
    }

    private function formatAuditSummary(BrandAudit $audit): array
    {
        return [
            'id'              => $audit->id,
            'status'          => $audit->status,
            'website_url'     => $audit->website_url,
            'brand_id'        => $audit->brand_id,
            'standalone_data' => $audit->standalone_data,
            'display_name'    => $audit->display_name,
            'display_sector'  => $audit->display_sector,
            'score_overall'   => $audit->score_overall,
            'score_label'     => $audit->score_label,
            'score_color'     => $audit->score_color,
            'score_neuromarketing'   => $audit->score_neuromarketing,
            'score_seo_geo'          => $audit->score_seo_geo,
            'score_gdpr'             => $audit->score_gdpr,
            'score_social_coherence' => $audit->score_social_coherence,
            'score_accessibility'    => $audit->score_accessibility,
            'score_performance'      => $audit->score_performance ?? null,
            'executive_summary' => $audit->executive_summary,
            'critical_issues'   => $audit->critical_issues  ?? [],
            'recommendations'   => $audit->recommendations  ?? [],
            'analyzer_results'  => $audit->analyzer_results ?? [],
            'error_message'     => $audit->error_message,
            'created_at'        => $audit->created_at?->toIso8601String(),
            'completed_at'      => $audit->completed_at?->toIso8601String(),
            'brand' => $audit->brand ? [
                'id'          => $audit->brand->id,
                'name'        => $audit->brand->name,
                'sector'      => $audit->brand->sector,
                'website_url' => $audit->brand->website_url ?: $audit->brand->website,
            ] : null,
            'organization' => $audit->brand?->organization ? [
                'id'   => $audit->brand->organization->id,
                'name' => $audit->brand->organization->name,
            ] : null,
        ];
    }

    private function formatAudit(BrandAudit $audit): array
    {
        $base = [
            'id'           => $audit->id,
            'brand_id'     => $audit->brand_id,
            'status'       => $audit->status,
            'website_url'  => $audit->website_url,
            'created_at'   => $audit->created_at?->toIso8601String(),
            'completed_at' => $audit->completed_at?->toIso8601String(),
        ];

        if ($audit->status === 'pending' || $audit->status === 'running') {
            return $base + ['message' => 'Audit in corso...'];
        }

        if ($audit->status === 'failed') {
            return $base + ['error' => $audit->error_message];
        }

        return $base + [
            'scores' => [
                'overall'          => $audit->score_overall,
                'label'            => $audit->score_label,
                'color'            => $audit->score_color,
                'neuromarketing'   => $audit->score_neuromarketing,
                'seo_geo'          => $audit->score_seo_geo,
                'social_coherence' => $audit->score_social_coherence,
                'gdpr'             => $audit->score_gdpr,
                'accessibility'    => $audit->score_accessibility,
                'performance'      => $audit->score_performance,
                'sector_key'       => $audit->analyzer_results['scoring_meta']['sector_key'] ?? null,
                'weights_used'     => $audit->analyzer_results['scoring_meta']['weights_used'] ?? null,
            ],
            'executive_summary' => $audit->executive_summary,
            'critical_issues'   => $audit->critical_issues ?? [],
            'recommendations'   => $audit->recommendations ?? [],
            'analyzer_results'  => $audit->analyzer_results ?? [],
        ];
    }
}
