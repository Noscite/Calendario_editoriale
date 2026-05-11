<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\AiUsage\Services\UsageAggregator;
use App\Domain\Organization\Models\Organization;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class AIUsageDashboard extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'AI Usage & Costi';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-euro';
    protected static ?int $navigationSort = 50;

    public ?int $selectedOrganizationId = null;
    public string $period = 'current_month';

    public function getView(): string
    {
        return 'filament.admin.pages.ai-usage-dashboard';
    }

    public function mount(): void
    {
        $this->selectedOrganizationId = Organization::query()->orderBy('id')->first()?->id;
    }

    public function getTitle(): string|Htmlable
    {
        return 'AI Usage & Margini';
    }

    public function getDataForView(): array
    {
        $aggregator = app(UsageAggregator::class);

        $org = $this->selectedOrganizationId
            ? Organization::find($this->selectedOrganizationId)
            : null;

        if (! $org) {
            return ['error' => 'Seleziona una organization'];
        }

        [$start, $end] = $this->periodRange();

        $orgCost   = $aggregator->costForOrganization($org->id, $start, $end);
        $topBrands = $aggregator->topConsumers($org->id, $start, $end, 10);
        $daily     = $aggregator->dailyCostForOrganization($org->id, days: 30);

        $planRevenue = $this->resolvePlanRevenue($org);

        $alertThreshold = (float) config('ai_pricing.alert_brand_monthly_cost_eur', 20.0);
        $brandsOverThreshold = $topBrands->filter(fn ($b) => $b['total_eur'] > $alertThreshold);

        return [
            'org'                   => $org,
            'org_cost'              => $orgCost,
            'top_brands'            => $topBrands,
            'daily'                 => $daily,
            'period_label'          => $this->periodLabel(),
            'plan_revenue_eur'      => $planRevenue,
            'gross_margin_pct'      => $planRevenue > 0
                ? round((($planRevenue - $orgCost['total_eur']) / $planRevenue) * 100, 1)
                : null,
            'alert_threshold'       => $alertThreshold,
            'brands_over_threshold' => $brandsOverThreshold,
        ];
    }

    private function periodRange(): array
    {
        return match ($this->period) {
            'last_30_days' => [now()->subDays(30), now()],
            'last_90_days' => [now()->subDays(90), now()],
            default        => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function periodLabel(): string
    {
        return match ($this->period) {
            'last_30_days' => 'Ultimi 30 giorni',
            'last_90_days' => 'Ultimi 90 giorni',
            default        => 'Mese corrente (' . now()->format('m/Y') . ')',
        };
    }

    private function resolvePlanRevenue(Organization $org): float
    {
        try {
            $plan = $org->plan;
            return (float) ($plan?->price_monthly ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'superuser';
    }
}
