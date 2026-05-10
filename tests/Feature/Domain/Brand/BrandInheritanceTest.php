<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Str;

function makeOrgForInheritance(array $attrs = []): Organization
{
    return Organization::create(array_merge([
        'name'             => 'Org Inh ' . uniqid(),
        'slug'             => 'org-inh-' . Str::random(8),
        'email'            => 'inh-' . Str::random(6) . '@test.com',
        'is_system_tenant' => false,
        'is_active'        => true,
    ], $attrs));
}

function makeBrandForOrg(int $orgId, array $attrs = []): Brand
{
    return Brand::withoutGlobalScope('organization')->create(array_merge([
        'organization_id' => $orgId,
        'name'            => 'Brand Inh ' . uniqid(),
    ], $attrs));
}

it('inherits default_vertical and default_territory_meta from organization at creation', function () {
    $org = makeOrgForInheritance([
        'default_vertical'        => 'unpli_regional',
        'default_territory_meta'  => ['region' => 'Lombardia'],
    ]);

    $brand = makeBrandForOrg($org->id);

    expect($brand->vertical)->toBe('unpli_regional');
    expect($brand->territory_meta)->toBe(['region' => 'Lombardia']);
});

it('does not override explicit vertical set on brand', function () {
    $org = makeOrgForInheritance([
        'default_vertical'        => 'unpli_regional',
        'default_territory_meta'  => ['region' => 'Lombardia'],
    ]);

    $brand = makeBrandForOrg($org->id, [
        'vertical'       => 'pro_loco',
        'territory_meta' => ['municipality_istat' => '015146'],
    ]);

    expect($brand->vertical)->toBe('pro_loco');
    expect($brand->territory_meta)->toBe(['municipality_istat' => '015146']);
});

it('does not inherit when organization has no default_vertical', function () {
    $org = makeOrgForInheritance(); // nessun default

    $brand = makeBrandForOrg($org->id);

    expect($brand->vertical)->toBeNull();
    expect($brand->territory_meta)->toBeNull();
});

it('does not re-apply inheritance on update', function () {
    $org = makeOrgForInheritance([
        'default_vertical'        => 'unpli_regional',
        'default_territory_meta'  => ['region' => 'Lombardia'],
    ]);

    $brand = makeBrandForOrg($org->id);
    expect($brand->vertical)->toBe('unpli_regional');

    // L'admin azzera il vertical sul brand
    $brand->update(['vertical' => null, 'territory_meta' => null]);

    // Ora l'org cambia default — il brand NON deve riereditare
    $org->update([
        'default_vertical'       => 'pro_loco',
        'default_territory_meta' => ['municipality_istat' => '999999'],
    ]);

    $brand->update(['name' => 'updated name']);

    expect($brand->fresh()->vertical)->toBeNull();
    expect($brand->fresh()->territory_meta)->toBeNull();
});

it('inherits vertical only when organization default_territory_meta is empty', function () {
    $org = makeOrgForInheritance([
        'default_vertical'       => 'unpli_regional',
        'default_territory_meta' => null,
    ]);

    $brand = makeBrandForOrg($org->id);

    expect($brand->vertical)->toBe('unpli_regional');
    expect($brand->territory_meta)->toBeNull();
});
