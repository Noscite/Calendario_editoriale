<?php

declare(strict_types=1);

namespace App\Domain\AiUsage\Services;

use App\Domain\Post\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Aggrega i costi AI da Post::generation_metadata.usage.
 *
 * Tutte le query usano Postgres JSON operators (->>'cost_eur') per estrarre
 * il valore senza scansione N+1 dei record.
 */
class UsageAggregator
{
    public function costForBrand(int $brandId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = Post::query()
            ->withoutGlobalScope('organization')
            ->join('projects', 'projects.id', '=', 'posts.project_id')
            ->where('projects.brand_id', $brandId)
            ->whereBetween('posts.created_at', [$startDate, $endDate])
            ->whereNotNull('posts.generation_metadata')
            ->selectRaw("
                COALESCE((posts.generation_metadata->'usage'->>'cost_eur')::numeric, 0)
                + COALESCE((posts.generation_metadata->'image_usage'->>'total_cost_eur')::numeric, 0) AS total_cost_eur,
                posts.generation_metadata->'usage'->>'purpose' AS purpose,
                posts.generation_metadata->'usage'->>'model' AS model
            ")
            ->get();

        return [
            'total_eur'  => (float) $rows->sum('total_cost_eur'),
            'post_count' => $rows->count(),
            'by_purpose' => $rows->groupBy('purpose')->map(fn ($g) => (float) $g->sum('total_cost_eur'))->toArray(),
            'by_model'   => $rows->groupBy('model')->map(fn ($g) => (float) $g->sum('total_cost_eur'))->toArray(),
        ];
    }

    public function costForProject(int $projectId): array
    {
        $rows = Post::query()
            ->withoutGlobalScope('organization')
            ->where('project_id', $projectId)
            ->whereNotNull('generation_metadata')
            ->selectRaw("
                COALESCE((generation_metadata->'usage'->>'cost_eur')::numeric, 0)
                + COALESCE((generation_metadata->'image_usage'->>'total_cost_eur')::numeric, 0) AS total_cost_eur,
                post_type,
                generation_metadata->'usage'->>'model' AS model
            ")
            ->get();

        return [
            'total_eur'    => (float) $rows->sum('total_cost_eur'),
            'post_count'   => $rows->count(),
            'by_post_type' => $rows->groupBy('post_type')->map(fn ($g) => (float) $g->sum('total_cost_eur'))->toArray(),
            'by_model'     => $rows->groupBy('model')->map(fn ($g) => (float) $g->sum('total_cost_eur'))->toArray(),
        ];
    }

    public function costForOrganization(int $orgId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = Post::query()
            ->withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('generation_metadata')
            ->selectRaw("
                COALESCE((generation_metadata->'usage'->>'cost_eur')::numeric, 0)
                + COALESCE((generation_metadata->'image_usage'->>'total_cost_eur')::numeric, 0) AS total_cost_eur,
                project_id
            ")
            ->get();

        return [
            'total_eur'  => (float) $rows->sum('total_cost_eur'),
            'post_count' => $rows->count(),
            'by_project' => $rows->groupBy('project_id')->map(fn ($g) => (float) $g->sum('total_cost_eur'))->toArray(),
        ];
    }

    /**
     * @return Collection<int, array{brand_id: int, brand_name: string, total_eur: float}>
     */
    public function topConsumers(int $orgId, CarbonInterface $startDate, CarbonInterface $endDate, int $limit = 10): Collection
    {
        return Post::query()
            ->withoutGlobalScope('organization')
            ->join('projects', 'projects.id', '=', 'posts.project_id')
            ->join('brands', 'brands.id', '=', 'projects.brand_id')
            ->where('posts.organization_id', $orgId)
            ->whereBetween('posts.created_at', [$startDate, $endDate])
            ->whereNotNull('posts.generation_metadata')
            ->selectRaw("
                brands.id AS brand_id,
                brands.name AS brand_name,
                SUM(
                    COALESCE((posts.generation_metadata->'usage'->>'cost_eur')::numeric, 0)
                    + COALESCE((posts.generation_metadata->'image_usage'->>'total_cost_eur')::numeric, 0)
                ) AS total_eur
            ")
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
        return Post::query()
            ->withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('generation_metadata')
            ->selectRaw("
                DATE(created_at) AS date,
                SUM(
                    COALESCE((generation_metadata->'usage'->>'cost_eur')::numeric, 0)
                    + COALESCE((generation_metadata->'image_usage'->>'total_cost_eur')::numeric, 0)
                ) AS total_eur
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'      => (string) $r->date,
                'total_eur' => (float) $r->total_eur,
            ]);
    }
}
