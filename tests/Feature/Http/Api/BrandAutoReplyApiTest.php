<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use Laravel\Sanctum\Sanctum;

function planForApiAutoReply(?int $monthlyReplyCount): Plan
{
    $key = $monthlyReplyCount === null ? 'unlimited' : (string) $monthlyReplyCount;
    return Plan::firstOrCreate(['name' => 'api-auto-' . $key], [
        'name'                         => 'api-auto-' . $key,
        'display_name'                 => 'API Auto ' . $key,
        'price_monthly'                => 50,
        'price_yearly'                 => 500,
        'max_brands'                   => 10,
        'max_users'                    => 10,
        'monthly_calendar_generations' => 100,
        'monthly_reply_count'          => $monthlyReplyCount,
        'monthly_text_tokens'          => 1000000,
        'monthly_images'               => 100,
        'is_active'                    => true,
        'allows_overage'               => false,
    ]);
}

function setupBrandApiWorld(?int $monthlyReplyCount = 100): array
{
    $plan         = planForApiAutoReply($monthlyReplyCount);
    [$user, $org] = createAuthenticatedUser([], ['plan_id' => $plan->id]);
    Subscription::create([
        'organization_id'        => $org->id,
        'plan_id'                => $plan->id,
        'status'                 => 'active',
        'paid_period_starts_at'  => now()->subMonth(),
        'paid_period_ends_at'    => now()->addMonth(),
    ]);
    $brand = createBrand($org);
    return [$user, $brand];
}

it('updates brand auto reply settings', function () {
    [$user, $brand] = setupBrandApiWorld();
    Sanctum::actingAs($user);

    $res = $this->putJson("/api/brands/{$brand->id}", [
        'auto_reply_enabled'                 => true,
        'auto_reply_min_rating'              => 5,
        'auto_reply_only_positive_sentiment' => false,
        'auto_reply_tone'                    => 'professional',
        'auto_reply_review_mode'             => true,
        'auto_reply_delay_minutes'           => 60,
    ]);

    $res->assertOk();

    $brand = Brand::withoutGlobalScope('organization')->find($brand->id);
    expect($brand->auto_reply_enabled)->toBeTrue();
    expect($brand->auto_reply_min_rating)->toBe(5);
    expect($brand->auto_reply_only_positive_sentiment)->toBeFalse();
    expect($brand->auto_reply_tone)->toBe('professional');
    expect($brand->auto_reply_review_mode)->toBeTrue();
    expect($brand->auto_reply_delay_minutes)->toBe(60);
});

it('validates min_rating range', function () {
    [$user, $brand] = setupBrandApiWorld();
    Sanctum::actingAs($user);

    $this->putJson("/api/brands/{$brand->id}", ['auto_reply_min_rating' => 2])
        ->assertStatus(422);

    $this->putJson("/api/brands/{$brand->id}", ['auto_reply_min_rating' => 6])
        ->assertStatus(422);
});

it('validates delay_minutes range', function () {
    [$user, $brand] = setupBrandApiWorld();
    Sanctum::actingAs($user);

    $this->putJson("/api/brands/{$brand->id}", ['auto_reply_delay_minutes' => 1])
        ->assertStatus(422);

    $this->putJson("/api/brands/{$brand->id}", ['auto_reply_delay_minutes' => 1500])
        ->assertStatus(422);
});

it('validates tone enum values', function () {
    [$user, $brand] = setupBrandApiWorld();
    Sanctum::actingAs($user);

    $this->putJson("/api/brands/{$brand->id}", ['auto_reply_tone' => 'cocktail-party'])
        ->assertStatus(422);
});

it('blocks enabling auto-reply when plan has zero quota', function () {
    [$user, $brand] = setupBrandApiWorld(monthlyReplyCount: 0);
    Sanctum::actingAs($user);

    $res = $this->putJson("/api/brands/{$brand->id}", [
        'auto_reply_enabled' => true,
    ]);

    $res->assertStatus(422)
        ->assertJsonPath('error', 'feature_unavailable');

    $brand = Brand::withoutGlobalScope('organization')->find($brand->id);
    expect($brand->auto_reply_enabled)->toBeFalse();
});
