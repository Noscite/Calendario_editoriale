<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;

describe('GET /api/brands', function () {
    it('lists brands for authenticated user organization', function () {
        [$user, $org] = createAuthenticatedUser();
        Brand::withoutGlobalScope('organization')->create([
            'organization_id' => $org->id,
            'name' => 'Brand Uno',
            'sector' => 'Tech',
        ]);
        Brand::withoutGlobalScope('organization')->create([
            'organization_id' => $org->id,
            'name' => 'Brand Due',
            'sector' => 'Food',
        ]);

        $response = $this->actingAs($user)->getJson('/api/brands');

        $response->assertOk()
            ->assertJsonCount(2);

        $names = collect($response->json())->pluck('name')->sort()->values();
        expect($names->toArray())->toBe(['Brand Due', 'Brand Uno']);
    });

    it('does not show brands from other organizations', function () {
        [$user, $org] = createAuthenticatedUser();

        // Altro org
        $otherOrg = \App\Domain\Organization\Models\Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'email' => 'other@org.com',
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        Brand::withoutGlobalScope('organization')->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Secret Brand',
        ]);

        $response = $this->actingAs($user)->getJson('/api/brands');

        $response->assertOk()
            ->assertJsonCount(0);
    });

    it('requires authentication', function () {
        $this->getJson('/api/brands')->assertStatus(401);
    });
});

describe('GET /api/brands/{id}', function () {
    it('shows a single brand', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, ['name' => 'Detail Brand']);

        $response = $this->actingAs($user)->getJson("/api/brands/{$brand->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Detail Brand']);
    });

    it('returns 404 for other org brand', function () {
        [$user, $org] = createAuthenticatedUser();

        $otherOrg = \App\Domain\Organization\Models\Organization::create([
            'name' => 'Other', 'slug' => 'other-2', 'email' => 'o2@test.com',
            'subscription_status' => 'active', 'is_active' => true,
        ]);

        $brand = Brand::withoutGlobalScope('organization')->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Forbidden',
        ]);

        $response = $this->actingAs($user)->getJson("/api/brands/{$brand->id}");

        $response->assertStatus(404);
    });
});

describe('POST /api/brands', function () {
    it('creates a brand', function () {
        [$user, $org] = createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson('/api/brands', [
            'name' => 'Nuovo Brand',
            'sector' => 'Marketing',
            'tone_of_voice' => 'amichevole',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Nuovo Brand']);

        expect(Brand::withoutGlobalScope('organization')->where('name', 'Nuovo Brand')->exists())->toBeTrue();
    });

    it('validates required fields', function () {
        [$user] = createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson('/api/brands', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('returns 400 when user has no organization', function () {
        $user = \App\Domain\User\Models\User::create([
            'email' => 'noorg@test.com',
            'password' => 'pass',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user)->postJson('/api/brands', [
            'name' => 'Should Fail',
        ]);

        $response->assertStatus(400);
    });
});

describe('PUT /api/brands/{id}', function () {
    it('updates a brand', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, ['name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
            'name' => 'New Name',
            'sector' => 'Finance',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);
    });
});

describe('DELETE /api/brands/{id}', function () {
    it('deletes a brand', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        $response = $this->actingAs($user)->deleteJson("/api/brands/{$brand->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Brand deleted']);

        // SoftDeletes
        expect(Brand::withoutGlobalScope('organization')->find($brand->id))->toBeNull();
    });
});
