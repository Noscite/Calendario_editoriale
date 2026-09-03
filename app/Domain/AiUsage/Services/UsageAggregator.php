<?php

declare(strict_types=1);

namespace App\Domain\AiUsage\Services;

use App\Domain\AiUsage\Models\AiUsageEvent;
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
}
