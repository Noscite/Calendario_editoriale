<?php

declare(strict_types=1);

use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;

describe('GET /api/projects', function () {
    it('lists all projects', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        createProject($brand, ['name' => 'Project A']);
        createProject($brand, ['name' => 'Project B']);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertOk();
        expect($response->json())->toHaveCount(2);
    });

    it('filters by brand_id', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand1 = createBrand($org, ['name' => 'B1']);
        $brand2 = createBrand($org, ['name' => 'B2']);
        createProject($brand1, ['name' => 'P1']);
        createProject($brand2, ['name' => 'P2']);

        $response = $this->actingAs($user)->getJson("/api/projects?brand_id={$brand1->id}");

        $response->assertOk();
        expect($response->json())->toHaveCount(1);
        expect($response->json('0.name'))->toBe('P1');
    });
});

describe('GET /api/projects/{id}', function () {
    it('returns project detail', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, ['name' => 'Detail Project']);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Detail Project'])
            ->assertJsonStructure([
                'id', 'brand_id', 'name', 'start_date', 'end_date',
                'platforms', 'status',
            ]);
    });
});

describe('POST /api/projects', function () {
    it('creates a project in draft status', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'brand_id' => $brand->id,
            'name' => 'New Project',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'platforms' => ['instagram', 'linkedin'],
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Project', 'status' => 'draft']);
    });

    it('validates required fields', function () {
        [$user] = createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson('/api/projects', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand_id', 'name', 'start_date', 'end_date']);
    });
});

describe('PUT /api/projects/{id}', function () {
    it('updates project fields', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, ['name' => 'Old']);

        $response = $this->actingAs($user)->putJson("/api/projects/{$project->id}", [
            'name' => 'Updated',
            'brief' => 'New brief content',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated']);
    });

    it('transitions status with valid transition', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, ['status' => 'draft']);

        $response = $this->actingAs($user)->putJson("/api/projects/{$project->id}", [
            'status' => 'generating',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'generating']);
    });

    it('rejects invalid status transition', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand, ['status' => 'draft']);

        $response = $this->actingAs($user)->putJson("/api/projects/{$project->id}", [
            'status' => 'published',
        ]);

        $response->assertStatus(422);
    });
});

describe('DELETE /api/projects/{id}', function () {
    it('deletes a project', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)->deleteJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Project deleted successfully']);

        expect(Project::find($project->id))->toBeNull();
    });
});
