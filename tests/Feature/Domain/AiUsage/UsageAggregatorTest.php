<?php

declare(strict_types=1);

use App\Domain\AiUsage\Models\AiUsageEvent;
use App\Domain\AiUsage\Services\UsageAggregator;

function makeUsageEvent(
    int $orgId,
    ?int $brandId,
    ?int $projectId,
    float $costEur,
    string $purpose,
    string $model = 'claude-sonnet-4-6',
    ?\Carbon\Carbon $createdAt = null,
): AiUsageEvent {
    return AiUsageEvent::create([
        'organization_id'       => $orgId,
        'brand_id'              => $brandId,
        'project_id'            => $projectId,
        'purpose'               => $purpose,
        'provider'              => 'anthropic',
        'model'                 => $model,
        'input_tokens'          => 3000,
        'output_tokens'         => 500,
        'cache_creation_tokens' => 0,
        'cache_read_tokens'     => 0,
        'cost_usd'              => $costEur / 0.93,
        'cost_eur'              => $costEur,
        'created_at'            => $createdAt ?? now(),
    ]);
}

beforeEach(function () {
    [, $org] = createAuthenticatedUser();
    $this->org = $org;
    $this->brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $this->project = createProject($this->brand, [
        'start_date' => '2026-05-01',
        'end_date'   => '2026-05-31',
    ]);

    $this->aggregator = app(UsageAggregator::class);
});

it('aggregates cost for brand in date range', function () {
    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.015, 'copy');
    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.018, 'event_post');
    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.012, 'copy');

    $result = $this->aggregator->costForBrand(
        $this->brand->id,
        now()->subDay(),
        now()->addDay(),
    );

    expect($result['total_eur'])->toBeGreaterThan(0.044);
    expect($result['total_eur'])->toBeLessThan(0.046);
    expect($result['event_count'])->toBe(3);
    expect($result['by_purpose']['copy'])->toBeGreaterThan(0.026);
});

it('aggregates cost for project broken down by purpose and model', function () {
    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.020, 'strategy');
    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.030, 'copy');

    $result = $this->aggregator->costForProject($this->project->id);

    expect($result['total_eur'])->toBeGreaterThan(0.049);
    expect($result['event_count'])->toBe(2);
    expect($result['by_purpose'])->toHaveKey('strategy');
    expect($result['by_purpose'])->toHaveKey('copy');
});

it('returns top consumers ordered by spend desc', function () {
    [, $org2] = createAuthenticatedUser();
    $brand2 = createBrand($org2, ['vertical' => 'pro_loco']);
    $project2 = createProject($brand2, ['start_date' => '2026-05-01', 'end_date' => '2026-05-31']);

    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.10, 'copy');
    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.20, 'copy');
    makeUsageEvent($org2->id, $brand2->id, $project2->id, 0.50, 'copy');

    $topOrg1 = $this->aggregator->topConsumers($this->org->id, now()->subDay(), now()->addDay(), 5);

    expect($topOrg1->count())->toBe(1);
    expect($topOrg1[0]['brand_id'])->toBe($this->brand->id);
    expect($topOrg1[0]['total_eur'])->toBeGreaterThan(0.29);
});

it('computes daily breakdown', function () {
    makeUsageEvent(
        $this->org->id, $this->brand->id, $this->project->id, 0.05, 'copy',
        createdAt: now()->subDays(2)->setTime(10, 0)
    );
    makeUsageEvent(
        $this->org->id, $this->brand->id, $this->project->id, 0.07, 'copy',
        createdAt: now()->subDays(1)->setTime(10, 0)
    );

    $daily = $this->aggregator->dailyCostForOrganization($this->org->id, days: 30);

    expect($daily->count())->toBeGreaterThanOrEqual(2);
    expect($daily->sum('total_eur'))->toBeGreaterThan(0.11);
});

it('breaks down cost by purpose and model', function () {
    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.05, 'copy', 'claude-opus-4-8');
    makeUsageEvent($this->org->id, $this->brand->id, $this->project->id, 0.02, 'strategy', 'claude-opus-4-7');

    $rows = $this->aggregator->costByPurposeAndModel($this->org->id, now()->subDay(), now()->addDay());

    expect($rows->count())->toBe(2);
    $copyRow = $rows->firstWhere('purpose', 'copy');
    expect($copyRow['model'])->toBe('claude-opus-4-8');
    expect($copyRow['calls'])->toBe(1);
});
