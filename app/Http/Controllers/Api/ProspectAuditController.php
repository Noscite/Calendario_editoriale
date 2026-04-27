<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Jobs\RunBrandAuditJob;
use App\Domain\Audit\Models\BrandAudit;
use App\Exceptions\BusinessException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

final class ProspectAuditController extends Controller
{
    /**
     * Avvia un audit prospect (senza brand).
     * POST /api/audit/prospect
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'    => 'required|url|max:500',
            'name'   => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
        ]);

        $running = BrandAudit::whereNull('brand_id')
            ->where('website_url', $validated['url'])
            ->where('status', 'running')
            ->exists();

        if ($running) {
            return response()->json(['error' => 'Audit già in corso per questo URL.'], 409);
        }

        try {
            $audit = BrandAudit::create([
                'brand_id'        => null,
                'prospect_name'   => $validated['name'],
                'prospect_sector' => $validated['sector'] ?? null,
                'website_url'     => $validated['url'],
                'status'          => 'pending',
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw new BusinessException(
                'Impossibile avviare l\'audit. Riprova tra qualche istante.',
                'AUDIT_GENERATION_FAILED',
                500,
                $e,
            );
        }

        try {
            RunBrandAuditJob::dispatch(null, $audit->id)->onQueue('audits');
        } catch (\Throwable $e) {
            // Il record è già creato — segnaliamo l'errore ma non cancelliamo il record.
            // L'utente può vedere lo stato "pending" e usare /rerun.
            report($e);
            throw new BusinessException(
                'Audit creato ma impossibile avviare l\'elaborazione. Usa "riavvia" tra qualche istante.',
                'AUDIT_GENERATION_FAILED',
                503,
                $e,
            );
        }

        return response()->json([
            'audit_id' => $audit->id,
            'status'   => 'pending',
            'message'  => 'Audit avviato. Tempo stimato: 2-3 minuti.',
        ], 202);
    }

    /**
     * Stato e risultato di un audit prospect (autenticato).
     * GET /api/audit/prospect/{id}
     */
    public function show(int $auditId, Request $request): JsonResponse
    {
        $audit = BrandAudit::whereNull('brand_id')->findOrFail($auditId);

        return response()->json($this->formatAudit($audit));
    }

    /**
     * Lista audit prospect dell'utente corrente (tutti quelli senza brand).
     * GET /api/audit/prospects
     */
    public function index(Request $request): JsonResponse
    {
        $audits = BrandAudit::whereNull('brand_id')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id'              => $a->id,
                'prospect_name'   => $a->prospect_name,
                'prospect_sector' => $a->prospect_sector,
                'website_url'     => $a->website_url,
                'status'          => $a->status,
                'score_overall'   => $a->score_overall,
                'score_label'     => $a->score_label,
                'score_color'     => $a->score_color,
                'share_token'     => $a->share_token,
                'created_at'      => $a->created_at?->toIso8601String(),
                'completed_at'    => $a->completed_at?->toIso8601String(),
            ]);

        return response()->json($audits);
    }

    /**
     * Genera (o rigenera) il link di condivisione pubblico.
     * POST /api/audit/prospect/{id}/share
     */
    public function generateShareLink(int $auditId, Request $request): JsonResponse
    {
        $audit = BrandAudit::whereNull('brand_id')->findOrFail($auditId);

        if (! $audit->isCompleted()) {
            return response()->json(['error' => 'L\'audit deve essere completato prima di condividerlo.'], 422);
        }

        $token = Str::random(48);
        $audit->update(['share_token' => $token]);

        return response()->json([
            'share_token' => $token,
            'share_url'   => url("/audit/share/{$token}"),
        ]);
    }

    /**
     * Rilancia un audit esistente (resetta a pending e redispatcha il job).
     * POST /api/audit/prospect/{id}/rerun
     */
    public function rerun(int $auditId, Request $request): JsonResponse
    {
        $audit = BrandAudit::whereNull('brand_id')->findOrFail($auditId);

        if (in_array($audit->status->value ?? $audit->status, ['pending', 'running'])) {
            return response()->json(['error' => 'Audit già in corso.'], 409);
        }

        $audit->update([
            'status'                  => 'pending',
            'score_overall'           => null,
            'score_neuromarketing'    => null,
            'score_seo_geo'           => null,
            'score_social_coherence'  => null,
            'score_gdpr'              => null,
            'score_accessibility'     => null,
            'score_performance'       => null,
            'executive_summary'       => null,
            'critical_issues'         => null,
            'recommendations'         => null,
            'analyzer_results'        => null,
            'raw_data'                => null,
            'error_message'           => null,
            'completed_at'            => null,
        ]);

        try {
            RunBrandAuditJob::dispatch(null, $audit->id)->onQueue('audits');
        } catch (\Throwable $e) {
            report($e);
            throw new BusinessException(
                'Impossibile rilanciare l\'audit. Riprova tra qualche istante.',
                'AUDIT_GENERATION_FAILED',
                503,
                $e,
            );
        }

        return response()->json(['status' => 'pending', 'message' => 'Audit rilanciato.']);
    }

    /**
     * Elimina un audit prospect.
     * DELETE /api/audit/prospect/{id}
     */
    public function destroy(int $auditId, Request $request): JsonResponse
    {
        $audit = BrandAudit::whereNull('brand_id')->findOrFail($auditId);

        if (in_array($audit->status->value ?? $audit->status, ['pending', 'running'])) {
            return response()->json(['error' => 'Non puoi eliminare un audit in corso. Attendi il completamento o riavvia il worker.'], 422);
        }

        $audit->delete();

        return response()->json(['message' => 'Audit eliminato.']);
    }

    /**
     * Visualizza un audit tramite token pubblico — NESSUNA AUTENTICAZIONE.
     * GET /api/audit/share/{token}
     */
    public function publicShow(string $token): JsonResponse
    {
        $audit = BrandAudit::where('share_token', $token)
            ->where('status', 'completed')
            ->firstOrFail();

        return response()->json($this->formatAudit($audit, public: true));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function formatAudit(BrandAudit $audit, bool $public = false): array
    {
        $base = [
            'id'              => $audit->id,
            'prospect_name'   => $audit->prospect_name,
            'prospect_sector' => $audit->prospect_sector,
            'brand_id'        => $audit->brand_id,
            'website_url'     => $audit->website_url,
            'status'          => $audit->status,
            'share_token'     => $public ? null : $audit->share_token,
            'created_at'      => $audit->created_at?->toIso8601String(),
            'completed_at'    => $audit->completed_at?->toIso8601String(),
        ];

        if (in_array($audit->status->value ?? $audit->status, ['pending', 'running'])) {
            return $base + ['message' => 'Audit in corso…'];
        }

        if (($audit->status->value ?? $audit->status) === 'failed') {
            return $base + ['error' => $audit->error_message];
        }

        return $base + [
            'scores' => [
                'overall'          => $audit->score_overall,
                'label'            => $audit->score_label,
                'color'            => $audit->score_color,
                'neuromarketing'   => $audit->score_neuromarketing,
                'seo_geo'          => $audit->score_seo_geo,
                'geo'              => $audit->score_geo,
                'social_coherence' => $audit->score_social_coherence,
                'gdpr'             => $audit->score_gdpr,
                'accessibility'    => $audit->score_accessibility,
                'performance'      => $audit->score_performance,
            ],
            'executive_summary' => $audit->executive_summary,
            'critical_issues'   => $audit->critical_issues   ?? [],
            'recommendations'   => $audit->recommendations   ?? [],
            'analyzer_results'  => $audit->analyzer_results  ?? [],
        ];
    }
}
