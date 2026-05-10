<?php

declare(strict_types=1);

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Exceptions\CampaignInvalidTransitionException;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Str;

function makeOrgForStateMachine(): Organization
{
    return Organization::create([
        'name'      => 'Org SM ' . uniqid(),
        'slug'      => 'org-sm-' . Str::random(8),
        'email'     => 'sm-' . Str::random(6) . '@test.com',
        'is_active' => true,
    ]);
}

function makeCampaignSM(CampaignStatus $status): Campaign
{
    $org = makeOrgForStateMachine();
    return Campaign::withoutGlobalScope('organization')->create([
        'organization_id' => $org->id,
        'name'            => 'Camp SM ' . uniqid(),
        'status'          => $status,
        'start_date'      => now()->addDays(7),
        'end_date'        => now()->addDays(37),
    ]);
}

it('allows draft to planning transition', function () {
    $campaign = makeCampaignSM(CampaignStatus::Draft);
    $campaign->update(['status' => CampaignStatus::Planning]);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Planning);
});

it('blocks invalid transitions like draft to completed', function () {
    $campaign = makeCampaignSM(CampaignStatus::Draft);

    expect(fn () => $campaign->update(['status' => CampaignStatus::Completed]))
        ->toThrow(CampaignInvalidTransitionException::class);
});

it('allows active to completed transition', function () {
    $campaign = makeCampaignSM(CampaignStatus::Active);

    $campaign->update(['status' => CampaignStatus::Completed]);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Completed);
});

it('blocks transitions out of archived (terminal state)', function () {
    $campaign = makeCampaignSM(CampaignStatus::Archived);

    expect(fn () => $campaign->update(['status' => CampaignStatus::Active]))
        ->toThrow(CampaignInvalidTransitionException::class);
});

it('allows reverting planning to draft', function () {
    $campaign = makeCampaignSM(CampaignStatus::Planning);
    $campaign->update(['status' => CampaignStatus::Draft]);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Draft);
});
