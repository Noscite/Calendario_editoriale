<?php

declare(strict_types=1);

use App\Domain\Brand\Data\CreateBrandData;
use App\Domain\Brand\Data\UpdateBrandData;

it('UpdateBrandData accepts null for tagline', function () {
    $data = UpdateBrandData::from([
        'name'    => 'Test Brand',
        'tagline' => null,
    ]);

    expect($data->tagline)->toBeNull();
});

it('UpdateBrandData accepts string for tagline', function () {
    $data = UpdateBrandData::from([
        'name'    => 'Test Brand',
        'tagline' => 'A nice tagline',
    ]);

    expect($data->tagline)->toBe('A nice tagline');
});

it('UpdateBrandData accepts missing tagline (Optional)', function () {
    $data = UpdateBrandData::from([
        'name' => 'Test Brand',
    ]);

    expect($data)->toBeInstanceOf(UpdateBrandData::class);
});

it('UpdateBrandData accepts null for all nullable scalar fields', function () {
    $data = UpdateBrandData::from([
        'name'                  => 'Test Brand',
        'sector'                => null,
        'tone_of_voice'         => null,
        'description'           => null,
        'website_url'           => null,
        'linkedin_url'          => null,
        'instagram_url'         => null,
        'facebook_url'          => null,
        'target_audience'       => null,
        'unique_selling_points' => null,
        'colors'                => null,
        'style_guide'           => null,
        'tagline'               => null,
    ]);

    expect($data->sector)->toBeNull();
    expect($data->tone_of_voice)->toBeNull();
    expect($data->description)->toBeNull();
    expect($data->website_url)->toBeNull();
    expect($data->linkedin_url)->toBeNull();
    expect($data->instagram_url)->toBeNull();
    expect($data->facebook_url)->toBeNull();
    expect($data->target_audience)->toBeNull();
    expect($data->unique_selling_points)->toBeNull();
    expect($data->colors)->toBeNull();
    expect($data->style_guide)->toBeNull();
    expect($data->tagline)->toBeNull();
});

it('CreateBrandData accepts null for tagline', function () {
    $data = CreateBrandData::from([
        'name'    => 'Test Brand',
        'sector'  => 'consulenza',
        'tagline' => null,
    ]);

    expect($data->tagline)->toBeNull();
});

it('CreateBrandData accepts string for tagline', function () {
    $data = CreateBrandData::from([
        'name'    => 'Test Brand',
        'sector'  => 'consulenza',
        'tagline' => 'Test tagline',
    ]);

    expect($data->tagline)->toBe('Test tagline');
});

it('CreateBrandData accepts null for all nullable scalar fields', function () {
    $data = CreateBrandData::from([
        'name'                  => 'Test Brand',
        'sector'                => null,
        'tone_of_voice'         => null,
        'description'           => null,
        'website_url'           => null,
        'linkedin_url'          => null,
        'instagram_url'         => null,
        'facebook_url'          => null,
        'target_audience'       => null,
        'unique_selling_points' => null,
        'colors'                => null,
        'style_guide'           => null,
        'tagline'               => null,
    ]);

    expect($data->sector)->toBeNull();
    expect($data->tone_of_voice)->toBeNull();
    expect($data->description)->toBeNull();
    expect($data->tagline)->toBeNull();
});

it('CreateBrandData accepts null for all nullable array fields', function () {
    $data = CreateBrandData::from([
        'name'                    => 'Test Brand',
        'brand_values'            => null,
        'voice_examples'          => null,
        'founder'                 => null,
        'narrative_assets'        => null,
        'default_content_pillars' => null,
        'forbidden_topics'        => null,
    ]);

    expect($data->brand_values)->toBeNull();
    expect($data->voice_examples)->toBeNull();
    expect($data->founder)->toBeNull();
    expect($data->narrative_assets)->toBeNull();
    expect($data->default_content_pillars)->toBeNull();
    expect($data->forbidden_topics)->toBeNull();
});

it('PUT /api/brands/{id} accepts null tagline (regression test wizard 422)', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['name' => 'Test', 'sector' => 'consulenza']);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'name'    => 'Test Updated',
        'sector'  => 'consulenza',
        'tagline' => null,
    ]);

    $response->assertOk();
});

it('PUT /api/brands/{id} accepts null for multiple optional string fields', function () {
    [$user, $org] = createAuthenticatedUser();
    $brand        = createBrand($org, ['name' => 'Test']);

    $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
        'name'                  => 'Updated',
        'tagline'               => null,
        'description'           => null,
        'unique_selling_points' => null,
        'style_guide'           => null,
    ]);

    $response->assertOk();
});
