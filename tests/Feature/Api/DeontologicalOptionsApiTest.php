<?php

declare(strict_types=1);

it('GET /api/deontological-options returns 5 regulated sectors', function () {
    [$user] = createAuthenticatedUser();

    $response = $this->actingAs($user)->getJson('/api/deontological-options');

    $response->assertOk();
    $response->assertJsonCount(5, 'data');
    $response->assertJsonFragment(['value' => 'psicologia']);
    $response->assertJsonFragment(['value' => 'finanza_indipendente']);
    $response->assertJsonFragment(['value' => 'legale']);
    $response->assertJsonFragment(['value' => 'salute']);
    $response->assertJsonFragment(['value' => 'finanza']);
});

it('GET /api/deontological-options requires authentication', function () {
    $this->getJson('/api/deontological-options')->assertStatus(401);
});

it('PUT /api/brands/{id} syncs deontological_constraints', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['name' => 'Test', 'sector' => 'consulenza']);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'name'                      => 'Test',
        'sector'                    => 'consulenza',
        'deontological_constraints' => ['psicologia', 'finanza_indipendente'],
    ]);

    $response->assertOk();

    $brand->refresh();
    $slugs = $brand->deontologicalConstraintSlugs()->toArray();
    expect($slugs)->toContain('psicologia');
    expect($slugs)->toContain('finanza_indipendente');
});

it('PUT /api/brands/{id} rejects invalid deontological_constraints values', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['name' => 'Test']);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'name'                      => 'Test',
        'deontological_constraints' => ['turismo', 'invalid_slug'],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['deontological_constraints.0', 'deontological_constraints.1']);
});

it('PUT /api/brands/{id} clears deontological_constraints when empty array is sent', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['name' => 'Test']);
    $brand->syncDeontologicalConstraints(['psicologia']);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'name'                      => 'Test',
        'deontological_constraints' => [],
    ]);

    $response->assertOk();
    $brand->refresh();
    expect($brand->hasDeontologicalConstraints())->toBeFalse();
});

it('PUT /api/brands/{id} preserves constraints when field is omitted', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['name' => 'Test']);
    $brand->syncDeontologicalConstraints(['psicologia']);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'name' => 'Updated Name',
    ]);

    $response->assertOk();
    $brand->refresh();
    expect($brand->deontologicalConstraintSlugs()->toArray())->toBe(['psicologia']);
});

it('GET /api/brands/{id} includes deontological_constraints in response', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['name' => 'Compliance Brand']);
    $brand->syncDeontologicalConstraints(['psicologia']);

    $response = $this->actingAs($user)->getJson("/api/brands/{$brand->id}");

    $response->assertOk();
    $response->assertJsonFragment(['deontological_constraints' => ['psicologia']]);
    $response->assertJsonFragment(['has_deontological_constraints' => true]);

    $labels = $response->json('deontological_constraints_labels');
    expect($labels)->toBeArray();
    expect($labels[0])->toMatchArray(['value' => 'psicologia']);
});

it('GET /api/brands lists include has_deontological_constraints flag', function () {
    [$user, $org] = createAuthenticatedUser();
    $brandA = createBrand($org, ['name' => 'A Regulated']);
    $brandA->syncDeontologicalConstraints(['legale']);
    createBrand($org, ['name' => 'B Plain']);

    $response = $this->actingAs($user)->getJson('/api/brands');

    $response->assertOk();
    $byName = collect($response->json())->keyBy('name');
    expect($byName['A Regulated']['has_deontological_constraints'])->toBeTrue();
    expect($byName['A Regulated']['deontological_constraints'])->toBe(['legale']);
    expect($byName['B Plain']['has_deontological_constraints'])->toBeFalse();
    expect($byName['B Plain']['deontological_constraints'])->toBe([]);
});

it('POST /api/brands persists deontological_constraints on creation', function () {
    [$user, $org] = createAuthenticatedUser();

    $response = $this->actingAs($user)->postJson('/api/brands', [
        'name'                      => 'New Brand',
        'sector'                    => 'consulenza',
        'deontological_constraints' => ['psicologia'],
    ]);

    $response->assertStatus(201);

    $brand = \App\Domain\Brand\Models\Brand::withoutGlobalScope('organization')
        ->where('name', 'New Brand')
        ->firstOrFail();

    expect($brand->deontologicalConstraintSlugs()->toArray())->toBe(['psicologia']);
});
