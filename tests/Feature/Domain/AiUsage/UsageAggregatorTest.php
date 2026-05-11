<?php

declare(strict_types=1);

use App\Domain\AiUsage\Services\UsageAggregator;
use App\Domain\Post\Models\Post;

function makePostWithUsage(int $projectId, int $orgId, float $costEur, string $purpose, string $model = 'claude-sonnet-4-6', ?\Carbon\Carbon $createdAt = null): Post
{
    $post = Post::withoutGlobalScope('organization')->create([
        'project_id'      => $projectId,
        'organization_id' => $orgId,
        'platform'        => 'linkedin',
        'post_type'       => 'storytelling',
        'title'           => 'Test',
        'content'         => 'Test content',
        'scheduled_date'  => now(),
        'status'          => 'draft',
        'generation_metadata' => [
            'source' => 'test',
            'usage'  => [
                'provider'      => 'anthropic',
                'model'         => $model,
                'input_tokens'  => 3000,
                'output_tokens' => 500,
                'cost_eur'      => $costEur,
                'cost_usd'      => $costEur / 0.93,
                'purpose'       => $purpose,
            ],
        ],
    ]);

    if ($createdAt) {
        $post->created_at = $createdAt;
        $post->save();
    }

    return $post;
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
    makePostWithUsage($this->project->id, $this->org->id, 0.015, 'calendar_base_post');
    makePostWithUsage($this->project->id, $this->org->id, 0.018, 'territorial_event_post');
    makePostWithUsage($this->project->id, $this->org->id, 0.012, 'calendar_base_post');

    $result = $this->aggregator->costForBrand(
        $this->brand->id,
        now()->subDay(),
        now()->addDay(),
    );

    expect($result['total_eur'])->toBeGreaterThan(0.044);
    expect($result['total_eur'])->toBeLessThan(0.046);
    expect($result['post_count'])->toBe(3);
    expect($result['by_purpose']['calendar_base_post'])->toBeGreaterThan(0.026);
});

it('aggregates cost for project broken down by post_type', function () {
    makePostWithUsage($this->project->id, $this->org->id, 0.020, 'p1');
    makePostWithUsage($this->project->id, $this->org->id, 0.030, 'p2');

    $result = $this->aggregator->costForProject($this->project->id);

    expect($result['total_eur'])->toBeGreaterThan(0.049);
    expect($result['post_count'])->toBe(2);
    expect($result['by_post_type'])->toHaveKey('storytelling');
});

it('returns top consumers ordered by spend desc', function () {
    [, $org2] = createAuthenticatedUser();
    $brand2 = createBrand($org2, ['vertical' => 'pro_loco']);
    $project2 = createProject($brand2, ['start_date' => '2026-05-01', 'end_date' => '2026-05-31']);

    makePostWithUsage($this->project->id, $this->org->id, 0.10, 'p');
    makePostWithUsage($this->project->id, $this->org->id, 0.20, 'p');
    makePostWithUsage($project2->id, $org2->id, 0.50, 'p');

    $topOrg1 = $this->aggregator->topConsumers($this->org->id, now()->subDay(), now()->addDay(), 5);

    expect($topOrg1->count())->toBe(1);
    expect($topOrg1[0]['brand_id'])->toBe($this->brand->id);
    expect($topOrg1[0]['total_eur'])->toBeGreaterThan(0.29);
});

it('computes daily breakdown', function () {
    makePostWithUsage(
        $this->project->id, $this->org->id, 0.05, 'p',
        createdAt: now()->subDays(2)->setTime(10, 0)
    );
    makePostWithUsage(
        $this->project->id, $this->org->id, 0.07, 'p',
        createdAt: now()->subDays(1)->setTime(10, 0)
    );

    $daily = $this->aggregator->dailyCostForOrganization($this->org->id, days: 30);

    expect($daily->count())->toBeGreaterThanOrEqual(2);
    expect($daily->sum('total_eur'))->toBeGreaterThan(0.11);
});

it('ignores posts without generation_metadata.usage', function () {
    // Post legacy senza usage
    Post::withoutGlobalScope('organization')->create([
        'project_id'      => $this->project->id,
        'organization_id' => $this->org->id,
        'platform'        => 'linkedin',
        'title'           => 'Legacy',
        'content'         => 'Legacy content',
        'scheduled_date'  => now(),
        'status'          => 'draft',
        'generation_metadata' => ['source' => 'legacy-no-usage'],
    ]);
    makePostWithUsage($this->project->id, $this->org->id, 0.05, 'p');

    $result = $this->aggregator->costForBrand(
        $this->brand->id,
        now()->subDay(),
        now()->addDay(),
    );

    expect($result['post_count'])->toBe(2);
    // Solo il post con usage contribuisce al total_eur
    expect($result['total_eur'])->toBeGreaterThan(0.049);
    expect($result['total_eur'])->toBeLessThan(0.051);
});
