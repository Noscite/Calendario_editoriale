<?php

declare(strict_types=1);

it('accepts null for array fields in update brand wizard payload', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'founder'                 => null,
        'brand_values'            => null,
        'voice_examples'          => null,
        'narrative_assets'        => null,
        'default_content_pillars' => null,
        'forbidden_topics'        => null,
    ]);

    $response->assertOk();
});

it('still validates array fields when value is non-array non-null', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'founder' => 'not-an-array-or-null',
    ]);

    $response->assertStatus(422);
});

it('accepts mixed null and valid arrays in same request', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'founder' => null,
        'narrative_assets' => [
            ['type' => 'event', 'name' => 'Sagra del Riso', 'details' => 'evento storico'],
        ],
    ]);

    $response->assertOk();
});
