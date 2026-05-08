<?php

declare(strict_types=1);

use App\Domain\Generation\Jobs\GenerateCalendarJob;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Facades\Bus;

describe('POST /api/generate/personas/{project_id}', function () {
    it('generates buyer personas for a project', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, ['platforms' => ['instagram', 'linkedin']]);

        $response = $this->actingAs($user)
            ->postJson("/api/generate/personas/{$project->id}");

        $response->assertOk()
            ->assertJsonStructure(['status', 'personas', 'message'])
            ->assertJsonFragment(['status' => 'generated']);
    });
});

describe('POST /api/generate/personas/{project_id}/regenerate', function () {
    it('regenerates personas with feedback', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)
            ->postJson("/api/generate/personas/{$project->id}/regenerate", [
                'feedback' => 'Voglio personas più giovani',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'regenerated']);
    });

    it('requires feedback field', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)
            ->postJson("/api/generate/personas/{$project->id}/regenerate", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['feedback']);
    });
});

describe('PUT /api/generate/personas/{project_id}/confirm', function () {
    it('confirms personas', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, [
            'buyer_personas' => ['personas' => [['name' => 'Persona1']]],
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/generate/personas/{$project->id}/confirm");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        $project->refresh();
        expect($project->buyer_personas['confirmed'])->toBeTrue();
    });
});

describe('GET /api/generate/personas/{project_id}', function () {
    it('returns saved personas', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, [
            'buyer_personas' => [
                'personas' => [['name' => 'CEO'], ['name' => 'CTO']],
                'confirmed' => true,
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/generate/personas/{$project->id}");

        $response->assertOk()
            ->assertJsonFragment(['confirmed' => true]);

        expect($response->json('personas.personas'))->toHaveCount(2);
    });
});

describe('POST /api/generate/calendar/{project_id}', function () {
    it('starts calendar generation', function () {
        Bus::fake([GenerateCalendarJob::class]);

        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        // Preflight: il brand deve avere le chiavi API obbligatorie altrimenti 422
        \App\Domain\Brand\Models\BrandApiKey::create(['brand_id' => $brand->id, 'key_name' => 'anthropic_api_key', 'encrypted_value' => 'sk-test-anthropic']);
        \App\Domain\Brand\Models\BrandApiKey::create(['brand_id' => $brand->id, 'key_name' => 'perplexity_api_key', 'encrypted_value' => 'pplx-test-perplexity']);

        $project = createProject($brand, [
            'buyer_personas' => ['personas' => [['name' => 'P1']], 'confirmed' => true],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/generate/calendar/{$project->id}");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'generating']);

        $project->refresh();
        expect($project->status->value)->toBe('generating');

        Bus::assertDispatched(GenerateCalendarJob::class);
    });

    it('starts calendar generation even when brand has no AI keys (env-served)', function () {
        Bus::fake([GenerateCalendarJob::class]);

        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org); // nessuna chiave configurata
        $project = createProject($brand, [
            'buyer_personas' => ['personas' => [['name' => 'P1']], 'confirmed' => true],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/generate/calendar/{$project->id}");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'generating']);

        $project->refresh();
        expect($project->status->value)->toBe('generating');

        Bus::assertDispatched(GenerateCalendarJob::class);
    });
});

describe('GET /api/generate/preflight/{project_id}', function () {
    it('returns can_generate=true regardless of brand AI keys (env-served)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org); // nessuna chiave configurata
        $project = createProject($brand);

        $response = $this->actingAs($user)
            ->getJson("/api/generate/preflight/{$project->id}");

        $response->assertOk()
            ->assertJsonFragment(['can_generate' => true]);
        expect($response->json('missing_keys'))->toBe([]);
    });

    it('returns can_generate=false only when project has no brand', function () {
        [$user, $org] = createAuthenticatedUser();

        // Lo schema DB ha projects.brand_id NOT NULL: per testare il caso
        // "no_brand" sfruttiamo Brand SoftDeletes. Dopo soft-delete, l'eager
        // load $project->brand ritorna null (global scope di SoftDeletes),
        // riproducendo esattamente la condizione di branching del controller.
        $brand   = createBrand($org);
        $project = createProject($brand);
        $brand->delete(); // soft delete

        $response = $this->actingAs($user)
            ->getJson("/api/generate/preflight/{$project->id}");

        $response->assertOk()
            ->assertJsonFragment(['can_generate' => false])
            ->assertJsonFragment(['reason' => 'no_brand']);
    });
});

describe('GET /api/generate/status/{project_id}', function () {
    it('returns generation status', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, ['status' => 'review']);

        $response = $this->actingAs($user)
            ->getJson("/api/generate/status/{$project->id}");

        $response->assertOk()
            ->assertJsonStructure(['status', 'post_count', 'percent'])
            ->assertJsonFragment(['status' => 'review', 'percent' => 100]);
    });
});

describe('POST /api/generate/personas/{project_id}/add', function () {
    it('adds a new persona', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, [
            'buyer_personas' => ['personas' => [['name' => 'Existing']]],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/generate/personas/{$project->id}/add", [
                'persona_description' => 'Giovane imprenditore',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'total_personas' => 2]);
    });
});

describe('DELETE /api/generate/personas/{project_id}/{index}', function () {
    it('deletes a persona by index', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, [
            'buyer_personas' => ['personas' => [['name' => 'A'], ['name' => 'B']]],
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/generate/personas/{$project->id}/0");

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'remaining_personas' => 1]);
    });

    it('returns 404 for invalid index', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, [
            'buyer_personas' => ['personas' => [['name' => 'Only']]],
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/generate/personas/{$project->id}/99");

        $response->assertStatus(404);
    });
});
