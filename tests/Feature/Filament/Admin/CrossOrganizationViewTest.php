<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\UsageLog;
use App\Filament\Admin\Resources\BrandResource;
use App\Filament\Admin\Resources\UsageLogResource;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    [$this->superAdmin, $orgPrimary] = createAuthenticatedUser(['role' => 'superuser']);

    // Brand nell'org del super-admin
    $this->brandPrimary = Brand::withoutGlobalScope('organization')->create([
        'organization_id' => $orgPrimary->id,
        'name'            => 'Brand Primary Org',
        'sector'          => 'tech',
    ]);

    // Org B con plan (createPlan ritorna esistente o crea, è idempotente)
    $plan = createPlan();
    $this->orgOther = Organization::create([
        'name'      => 'Other Org Test',
        'slug'      => 'other-org-' . Str::random(8),
        'email'     => 'otherorg-' . Str::random(8) . '@test.com',
        'plan_id'   => $plan->id,
        'is_active' => true,
    ]);

    $this->brandOther = Brand::withoutGlobalScope('organization')->create([
        'organization_id' => $this->orgOther->id,
        'name'            => 'Brand Other Org',
        'sector'          => 'finance',
    ]);
});

it('BrandResource shows brands from ALL organizations to super-admin', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(BrandResource\Pages\ListBrands::class)
        ->assertCanSeeTableRecords([$this->brandPrimary, $this->brandOther]);
});

it('BrandResource can filter by organization', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(BrandResource\Pages\ListBrands::class)
        ->filterTable('organization_id', $this->orgOther->id)
        ->assertCanSeeTableRecords([$this->brandOther])
        ->assertCanNotSeeTableRecords([$this->brandPrimary]);
});

it('UsageLogResource shows logs from ALL organizations to super-admin', function () {
    $logPrimary = UsageLog::withoutGlobalScope('organization')->create([
        'organization_id'  => $this->brandPrimary->organization_id,
        'period_start'     => now()->startOfMonth(),
        'period_end'       => now()->endOfMonth(),
        'text_tokens_used' => 1000,
    ]);
    $logOther = UsageLog::withoutGlobalScope('organization')->create([
        'organization_id'  => $this->orgOther->id,
        'period_start'     => now()->startOfMonth(),
        'period_end'       => now()->endOfMonth(),
        'text_tokens_used' => 2000,
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(UsageLogResource\Pages\ListUsageLogs::class)
        ->assertCanSeeTableRecords([$logPrimary, $logOther]);
});

it('BrandResource getEloquentQuery bypasses the organization global scope', function () {
    $this->actingAs($this->superAdmin);

    $count = BrandResource::getEloquentQuery()->count();

    // Almeno i 2 brand creati nei due org distinti
    expect($count)->toBeGreaterThanOrEqual(2);
});
