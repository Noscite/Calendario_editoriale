<?php

declare(strict_types=1);

use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Subscription\Services\PostCreditService;

beforeEach(function () {
    [$this->user, $this->org] = createAuthenticatedUser();
    $this->brand = createBrand($this->org);
    BrandApiKey::create(['brand_id' => $this->brand->id, 'key_name' => 'anthropic_api_key', 'encrypted_value' => 'sk-test']);
    BrandApiKey::create(['brand_id' => $this->brand->id, 'key_name' => 'perplexity_api_key', 'encrypted_value' => 'pplx-test']);
    $this->service = app(PostCreditService::class);
});

it('preflight still allows generation for a non-enrolled organization even at implicit zero balance', function () {
    $project = createProject($this->brand, [
        'platforms' => ['linkedin'],
        'posts_per_week' => ['linkedin' => 3],
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]);

    $response = $this->actingAs($this->user)->getJson("/api/generate/preflight/{$project->id}");

    $response->assertOk()->assertJsonFragment(['can_generate' => true]);
});

it('preflight blocks generation once enrolled with insufficient credit', function () {
    $this->service->credit($this->org->id, 2, 'purchase');

    $project = createProject($this->brand, [
        'platforms' => ['linkedin', 'instagram'],
        'posts_per_week' => ['linkedin' => 3, 'instagram' => 2],
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-14', // 2 settimane → stima (3+2)*2 = 10
    ]);

    $response = $this->actingAs($this->user)->getJson("/api/generate/preflight/{$project->id}");

    $response->assertOk()
        ->assertJsonFragment(['can_generate' => false])
        ->assertJsonFragment(['reason' => 'insufficient_credit'])
        ->assertJsonFragment(['credit_balance' => 2])
        ->assertJsonFragment(['credit_needed' => 10]);
});

it('preflight allows generation once enrolled with sufficient credit', function () {
    $this->service->credit($this->org->id, 100, 'purchase');

    $project = createProject($this->brand, [
        'platforms' => ['linkedin'],
        'posts_per_week' => ['linkedin' => 2],
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-07',
    ]);

    $response = $this->actingAs($this->user)->getJson("/api/generate/preflight/{$project->id}");

    $response->assertOk()->assertJsonFragment(['can_generate' => true]);
});

it('POST generate/calendar returns 422 insufficient_credit when enrolled and short on balance', function () {
    $this->service->credit($this->org->id, 1, 'purchase');

    $project = createProject($this->brand, [
        'platforms' => ['linkedin', 'instagram', 'facebook'],
        'posts_per_week' => ['linkedin' => 3, 'instagram' => 3, 'facebook' => 3],
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-07',
    ]);

    $response = $this->actingAs($this->user)->postJson("/api/generate/calendar/{$project->id}");

    $response->assertStatus(422)->assertJsonFragment(['status' => 'insufficient_credit']);
});
