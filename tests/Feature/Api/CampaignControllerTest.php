<?php

declare(strict_types=1);

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Support\Str;

function makeOtherOrg(): Organization
{
    return Organization::create([
        'name'      => 'Other Org ' . uniqid(),
        'slug'      => 'other-org-' . Str::random(8),
        'email'     => 'other-' . Str::random(6) . '@test.com',
        'is_active' => true,
    ]);
}

function makeCampaign(Organization $org, array $attrs = []): Campaign
{
    return Campaign::withoutGlobalScope('organization')->create(array_merge([
        'organization_id' => $org->id,
        'name'            => 'Camp ' . uniqid(),
        'status'          => 'draft',
        'start_date'      => now()->addDays(7),
        'end_date'        => now()->addDays(37),
    ], $attrs));
}

it('lists campaigns scoped to user organization', function () {
    [$user, $org] = createAuthenticatedUser();

    makeCampaign($org, ['name' => 'My Campaign 1', 'status' => 'draft']);
    makeCampaign($org, ['name' => 'My Campaign 2', 'status' => 'active']);

    $otherOrg = makeOtherOrg();
    makeCampaign($otherOrg, ['name' => 'Other Org Campaign', 'status' => 'draft']);

    $response = $this->actingAs($user)->getJson('/api/campaigns');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('My Campaign 1');
    expect($names)->toContain('My Campaign 2');
});

it('creates campaign in draft status with brand pivot', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    $response = $this->actingAs($user)->postJson('/api/campaigns', [
        'name'        => 'Lancio Primavera',
        'description' => 'Campagna stagionale',
        'start_date'  => '2026-06-01',
        'end_date'    => '2026-08-31',
        'brand_ids'   => [$brand->id],
    ]);

    $response->assertCreated();
    expect($response->json('status'))->toBe('draft');
    expect($response->json('brands'))->toHaveCount(1);
    expect($response->json('brands.0.id'))->toBe($brand->id);
})->skip('Refactor unify-campaign: POST /api/campaigns rimosso. Creazione ora SOLO via POST /api/projects/{id}/campaigns (vedi ProjectCampaignApiTest).');

it('rejects creating campaign over plan limit', function () {
    // Crea piano Small (max=1) e org con quel piano
    $plan = Plan::create([
        'name'                         => 'small-' . Str::random(4),
        'display_name'                 => 'Small',
        'price_monthly'                => 29.00,
        'price_yearly'                 => 290.00,
        'monthly_calendar_generations' => 10,
        'monthly_text_tokens'          => 100_000,
        'monthly_images'               => 50,
        'is_active'                    => true,
        'allows_overage'               => false,
        'max_active_campaigns'         => 1,
    ]);

    [$user, $org] = createAuthenticatedUser([], ['plan_id' => $plan->id]);

    makeCampaign($org, ['name' => 'First', 'status' => 'active']);

    // Per andare oltre il limite, devo creare la seconda direttamente attiva.
    // Il store di default crea sempre draft, quindi simulo via update post-create.
    $response = $this->actingAs($user)->postJson('/api/campaigns', [
        'name' => 'Second draft (ok)',
    ]);
    $response->assertCreated();
    $secondId = $response->json('id');

    // Ora tento la transizione in active
    $response2 = $this->actingAs($user)->putJson("/api/campaigns/{$secondId}", [
        'status' => 'planning',
    ]);

    $response2->assertStatus(422);
    expect($response2->json('error.code'))->toBe('CAMPAIGN_LIMIT_REACHED');
})->skip('Refactor unify-campaign: POST /api/campaigns rimosso. Plan limit ora gestito via CampaignObserver alla creazione tramite /api/projects/{id}/campaigns.');

it('rejects status transition draft to completed', function () {
    [$user, $org] = createAuthenticatedUser();
    $campaign = makeCampaign($org, ['status' => 'draft']);

    $response = $this->actingAs($user)->putJson("/api/campaigns/{$campaign->id}", [
        'status' => 'completed',
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('INVALID_STATE_TRANSITION');
});

it('rejects deleting active campaign', function () {
    [$user, $org] = createAuthenticatedUser();
    $campaign = makeCampaign($org, ['status' => 'active']);

    $response = $this->actingAs($user)->deleteJson("/api/campaigns/{$campaign->id}");

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('CAMPAIGN_NOT_DELETABLE');
});

it('allows deleting draft campaign', function () {
    [$user, $org] = createAuthenticatedUser();
    $campaign = makeCampaign($org, ['status' => 'draft']);

    $response = $this->actingAs($user)->deleteJson("/api/campaigns/{$campaign->id}");

    $response->assertOk();
    expect(Campaign::withoutGlobalScope('organization')->find($campaign->id))->toBeNull();
});

it('cannot access campaign of another organization', function () {
    [$user, $org] = createAuthenticatedUser();
    $otherOrg = makeOtherOrg();
    $campaign = makeCampaign($otherOrg, ['status' => 'draft']);

    $response = $this->actingAs($user)->getJson("/api/campaigns/{$campaign->id}");

    $response->assertNotFound();
});

it('filters out brand_ids that belong to another organization', function () {
    [$user, $org] = createAuthenticatedUser();
    $otherOrg = makeOtherOrg();
    $myBrand = createBrand($org);
    $otherBrand = createBrand($otherOrg);

    $response = $this->actingAs($user)->postJson('/api/campaigns', [
        'name'      => 'Sneaky',
        'brand_ids' => [$myBrand->id, $otherBrand->id],
    ]);

    $response->assertCreated();
    $brands = $response->json('brands');
    expect($brands)->toHaveCount(1);
    expect($brands[0]['id'])->toBe($myBrand->id);
})->skip('Refactor unify-campaign: POST /api/campaigns rimosso. La creazione usa project.brand_id (single brand) invece di brand_ids[] array.');
