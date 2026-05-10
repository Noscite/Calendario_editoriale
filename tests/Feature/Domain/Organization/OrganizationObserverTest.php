<?php

declare(strict_types=1);

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Str;

function makeOrgForObserver(array $attrs = []): Organization
{
    return Organization::create(array_merge([
        'name'             => 'Org Obs ' . uniqid(),
        'slug'             => 'org-obs-' . Str::random(8),
        'email'            => 'obs-' . Str::random(6) . '@test.com',
        'is_system_tenant' => false,
        'is_active'        => true,
    ], $attrs));
}

it('creates a Subscription when Organization is created with status active', function () {
    $org = makeOrgForObserver([
        'subscription_status' => OrganizationStatus::Active,
    ]);

    $sub = Subscription::where('organization_id', $org->id)->first();

    expect($sub)->not->toBeNull();
    expect($sub->status)->toBe(Subscription::STATUS_ACTIVE);
});

it('creates a Subscription with trial status mapped correctly', function () {
    $org = makeOrgForObserver([
        'subscription_status' => OrganizationStatus::Trial,
        'trial_ends_at'       => now()->addDays(14),
    ]);

    $sub = $org->subscription;

    expect($sub)->not->toBeNull();
    expect($sub->status)->toBe(Subscription::STATUS_TRIAL);
    expect($sub->trial_ends_at)->not->toBeNull();
});

it('does not create a Subscription when Organization has no subscription_status', function () {
    $org = makeOrgForObserver();

    expect($org->subscription)->toBeNull();
});

it('maps PastDue and Suspended to pending_payment', function () {
    $past = makeOrgForObserver([
        'subscription_status' => OrganizationStatus::PastDue,
    ]);
    $susp = makeOrgForObserver([
        'subscription_status' => OrganizationStatus::Suspended,
    ]);

    expect($past->subscription->status)->toBe(Subscription::STATUS_PENDING_PAYMENT);
    expect($susp->subscription->status)->toBe(Subscription::STATUS_PENDING_PAYMENT);
});

it('does not duplicate Subscription if one already exists', function () {
    $org = makeOrgForObserver([
        'subscription_status' => OrganizationStatus::Active,
    ]);

    expect(Subscription::where('organization_id', $org->id)->count())->toBe(1);

    // Una seconda chiamata al created (simulata) non duplica
    (new \App\Domain\Organization\Observers\OrganizationObserver())->created($org);

    expect(Subscription::where('organization_id', $org->id)->count())->toBe(1);
});
