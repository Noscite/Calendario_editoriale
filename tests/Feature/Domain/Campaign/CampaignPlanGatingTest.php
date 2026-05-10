<?php

declare(strict_types=1);

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Exceptions\CampaignLimitExceededException;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Support\Str;

function makePlan(string $name, ?int $maxActive): Plan
{
    return Plan::create([
        'name'                         => $name,
        'display_name'                 => ucfirst($name),
        'price_monthly'                => 29.00,
        'price_yearly'                 => 290.00,
        'monthly_calendar_generations' => 10,
        'monthly_text_tokens'          => 100_000,
        'monthly_images'               => 50,
        'is_active'                    => true,
        'allows_overage'               => false,
        'max_active_campaigns'         => $maxActive,
    ]);
}

function makeOrgWithPlan(int $planId): Organization
{
    return Organization::create([
        'name'      => 'Org PG ' . uniqid(),
        'slug'      => 'org-pg-' . Str::random(8),
        'email'     => 'pg-' . Str::random(6) . '@test.com',
        'plan_id'   => $planId,
        'is_active' => true,
    ]);
}

function makeCampaignFor(Organization $org, CampaignStatus $status): Campaign
{
    return Campaign::withoutGlobalScope('organization')->create([
        'organization_id' => $org->id,
        'name'            => 'Camp ' . uniqid(),
        'status'          => $status,
        'start_date'      => now()->addDays(7),
        'end_date'        => now()->addDays(37),
    ]);
}

beforeEach(function () {
    $this->planSmall     = makePlan('small-' . Str::random(4), 1);
    $this->planPro       = makePlan('pro-' . Str::random(4), 10);
    $this->planUnlimited = makePlan('unlim-' . Str::random(4), null);
});

it('allows draft campaigns regardless of plan limit', function () {
    $org = makeOrgWithPlan($this->planSmall->id);

    for ($i = 0; $i < 5; $i++) {
        makeCampaignFor($org, CampaignStatus::Draft);
    }

    expect(Campaign::withoutGlobalScope('organization')->where('organization_id', $org->id)->count())->toBe(5);
});

it('blocks creation of an active campaign when small plan limit reached', function () {
    $org = makeOrgWithPlan($this->planSmall->id);

    makeCampaignFor($org, CampaignStatus::Active);

    expect(fn () => makeCampaignFor($org, CampaignStatus::Active))
        ->toThrow(CampaignLimitExceededException::class);
});

it('allows creating active campaigns up to pro plan limit', function () {
    $org = makeOrgWithPlan($this->planPro->id);

    for ($i = 0; $i < 10; $i++) {
        makeCampaignFor($org, CampaignStatus::Active);
    }

    expect(fn () => makeCampaignFor($org, CampaignStatus::Active))
        ->toThrow(CampaignLimitExceededException::class);
});

it('allows unlimited active campaigns when plan max is null', function () {
    $org = makeOrgWithPlan($this->planUnlimited->id);

    for ($i = 0; $i < 50; $i++) {
        makeCampaignFor($org, CampaignStatus::Active);
    }

    expect(Campaign::withoutGlobalScope('organization')->where('organization_id', $org->id)->count())->toBe(50);
});

it('does not count completed and archived campaigns toward limit', function () {
    $org = makeOrgWithPlan($this->planSmall->id);

    for ($i = 0; $i < 5; $i++) {
        makeCampaignFor($org, CampaignStatus::Completed);
    }
    for ($i = 0; $i < 3; $i++) {
        makeCampaignFor($org, CampaignStatus::Archived);
    }

    // Limite Small=1 attiva, dovrebbe permettere 1 active in più
    makeCampaignFor($org, CampaignStatus::Active);

    expect(Campaign::withoutGlobalScope('organization')->where('organization_id', $org->id)->count())->toBe(9);
});
