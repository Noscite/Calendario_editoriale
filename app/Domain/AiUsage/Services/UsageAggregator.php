<?php

declare(strict_types=1);

namespace App\Domain\AiUsage\Services;

use App\Domain\AiUsage\Models\AiUsageEvent;
use App\Domain\Subscription\Models\UsageLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Aggrega i costi AI reali da ai_usage_events — log append-only di OGNI
 * chiamata Anthropic (personas, strategy, copy, regenerate, image_prompt,
 * territorial...), non solo di quelle che producono un Post.
 *
 * Prima di questa tabella (introdotta 2026-08-21) i costi venivano letti da
 * Post::generation_metadata->usage, che il generatore principale
 * (ClaudeContentGenerator) non valorizzava mai: la dashboard mostrava solo
 * la fetta Territorial/Campaign, sottostimando il consumo reale.
 */
class UsageAggregator
{
    public function costForBrand(int $brandId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = AiUsageEvent::query()
            ->where('brand_id', $brandId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'total_eur'  => (float) $rows->sum('cost_eur'),
            'event_count' => $rows->count(),
            'by_purpose' => $rows->groupBy('purpose')->map(fn ($g) => (float) $g->sum('cost_eur'))->toArray(),
            'by_model'   => $rows->groupBy('model')->map(fn ($g) => (float) $g->sum('cost_eur'))->toArray(),
        ];
    }

    public function costForProject(int $projectId): array
    {
        $rows = AiUsageEvent::query()->where('project_id', $projectId)->get();

        return [
            'total_eur'   => (float) $rows->sum('cost_eur'),
            'event_count' => $rows->count(),
            'by_purpose'  => $rows->groupBy('purpose')->map(fn ($g) => (float) $g->sum('cost_eur'))->toArray(),
            'by_model'    => $rows->groupBy('model')->map(fn ($g) => (float) $g->sum('cost_eur'))->toArray(),
        ];
    }

    public function costForOrganization(int $orgId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = AiUsageEvent::query()
            ->where('organization_id', $orgId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'total_eur'   => (float) $rows->sum('cost_eur'),
            'event_count' => $rows->count(),
            'by_project'  => $rows->groupBy('project_id')->map(fn ($g) => (float) $g->sum('cost_eur'))->toArray(),
            'by_purpose'  => $rows->groupBy('purpose')->map(fn ($g) => (float) $g->sum('cost_eur'))->toArray(),
            'by_model'    => $rows->groupBy('model')->map(fn ($g) => (float) $g->sum('cost_eur'))->toArray(),
        ];
    }

    /**
     * @return Collection<int, array{brand_id: int, brand_name: string, total_eur: float}>
     */
    public function topConsumers(int $orgId, CarbonInterface $startDate, CarbonInterface $endDate, int $limit = 10): Collection
    {
        return AiUsageEvent::query()
            ->join('brands', 'brands.id', '=', 'ai_usage_events.brand_id')
            ->where('ai_usage_events.organization_id', $orgId)
            ->whereBetween('ai_usage_events.created_at', [$startDate, $endDate])
            ->selectRaw('brands.id AS brand_id, brands.name AS brand_name, SUM(ai_usage_events.cost_eur) AS total_eur')
            ->groupBy('brands.id', 'brands.name')
            ->orderByDesc('total_eur')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'brand_id'   => (int) $r->brand_id,
                'brand_name' => $r->brand_name,
                'total_eur'  => (float) $r->total_eur,
            ]);
    }

    /**
     * @return Collection<int, array{date: string, total_eur: float}>
     */
    public function dailyCostForOrganization(int $orgId, int $days = 30): Collection
    {
        return AiUsageEvent::query()
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) AS date, SUM(cost_eur) AS total_eur')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'      => (string) $r->date,
                'total_eur' => (float) $r->total_eur,
            ]);
    }

    /**
     * Breakdown per step di generazione (purpose), utile per capire dove
     * si concentra il costo e valutare se conviene un downgrade di modello.
     *
     * @return Collection<int, array{purpose: string, model: string, total_eur: float, calls: int}>
     */
    public function costByPurposeAndModel(int $orgId, CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        return AiUsageEvent::query()
            ->where('organization_id', $orgId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('purpose, model, SUM(cost_eur) AS total_eur, COUNT(*) AS calls')
            ->groupBy('purpose', 'model')
            ->orderByDesc('total_eur')
            ->get()
            ->map(fn ($r) => [
                'purpose'   => $r->purpose,
                'model'     => $r->model,
                'total_eur' => (float) $r->total_eur,
                'calls'     => (int) $r->calls,
            ]);
    }

    /**
     * Fallback quando ai_usage_events non ha ancora dati (es. subito dopo il
     * deploy del tracking, prima che sia partita una nuova generazione): usa
     * i contatori grezzi di usage_tracking, popolati da GenerateCalendarJob
     * e DalleImageGenerator fin da prima dell'introduzione di ai_usage_events.
     *
     * Granularità per BRAND, non per organizzazione: un'organizzazione con
     * più brand deve poter vedere quale brand specifico ha consumato, non
     * solo il totale aggregato. brand_id è nullable in usage_tracking (righe
     * storiche pre-migrazione, o organizzazioni senza brand associato): quelle
     * righe vengono raggruppate sotto "— (non attribuito a un brand)".
     *
     * A differenza di costByPurposeAndModel(), qui il costo è una STIMA a
     * range (min/max), non un importo esatto: usage_tracking salva solo il
     * totale di token input+output per brand/mese, senza lo split tra i due
     * (che ha tariffe molto diverse) né il modello usato per ogni chiamata.
     * Cross-tenant per default (un superuser deve poter vedere quali brand
     * hanno consumato su tutta la piattaforma), ma filtrabile su una singola
     * organizzazione — es. la pagina AI Usage Dashboard, che ha già un
     * selettore organizzazione e deve mostrare solo i suoi brand, non tutti.
     *
     * @return Collection<int, array{brand_id:?int, brand_name:string, organization_name:string, calendar_generations:int, text_tokens:int, images:int, cost_min_eur:float, cost_max_eur:float}>
     */
    public function rawUsageByBrand(CarbonInterface $startDate, CarbonInterface $endDate, ?int $organizationId = null): Collection
    {
        $pricing  = config('ai_pricing.anthropic.claude-opus-4-8', config('ai_pricing.anthropic.default'));
        $usdToEur = (float) config('ai_pricing.usd_to_eur', 0.93);
        $imgCost  = (float) config('ai_pricing.openai_images.default', 0.04);

        return UsageLog::query()
            ->withoutGlobalScope('organization')
            ->join('organizations', 'organizations.id', '=', 'usage_tracking.organization_id')
            ->leftJoin('brands', 'brands.id', '=', 'usage_tracking.brand_id')
            ->when($organizationId !== null, fn ($q) => $q->where('usage_tracking.organization_id', $organizationId))
            ->where('usage_tracking.period_start', '<=', $endDate->toDateString())
            ->where('usage_tracking.period_end', '>=', $startDate->toDateString())
            ->where(function ($q) {
                $q->where('usage_tracking.text_tokens_used', '>', 0)
                  ->orWhere('usage_tracking.images_generated', '>', 0);
            })
            ->selectRaw('usage_tracking.brand_id AS brand_id, brands.name AS brand_name, '
                . 'organizations.id AS organization_id, organizations.name AS organization_name, '
                . 'SUM(usage_tracking.calendar_generations_used) AS calendar_generations, '
                . 'SUM(usage_tracking.text_tokens_used) AS text_tokens, '
                . 'SUM(usage_tracking.images_generated) AS images')
            ->groupBy('usage_tracking.brand_id', 'brands.name', 'organizations.id', 'organizations.name')
            ->orderByDesc('text_tokens')
            ->get()
            ->map(function ($r) use ($pricing, $usdToEur, $imgCost) {
                $tokens  = (int) $r->text_tokens;
                $images  = (int) $r->images;
                $minUsd  = ($tokens / 1_000_000) * $pricing['input'];
                $maxUsd  = ($tokens / 1_000_000) * $pricing['output'];
                $imgUsd  = $images * $imgCost;

                return [
                    'brand_id'             => $r->brand_id !== null ? (int) $r->brand_id : null,
                    'brand_name'           => $r->brand_name ?? '— (non attribuito a un brand)',
                    'organization_id'      => (int) $r->organization_id,
                    'organization_name'    => $r->organization_name,
                    'calendar_generations' => (int) $r->calendar_generations,
                    'text_tokens'          => $tokens,
                    'images'               => $images,
                    'cost_min_eur'         => ($minUsd + $imgUsd) * $usdToEur,
                    'cost_max_eur'         => ($maxUsd + $imgUsd) * $usdToEur,
                ];
            });
    }
}
