<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;

describe('POST /api/projects/{id}/editions', function () {
    it('creates a new edition from a parent project', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $parent = createProject($brand, ['name' => 'Piano Marzo']);

        $response = $this->actingAs($user)->postJson("/api/projects/{$parent->id}/editions", [
            'name' => 'Piano Aprile',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Piano Aprile',
                'edition_number' => 2,
                'parent_project_id' => $parent->id,
            ]);

        // Parent should have been assigned edition_number 1
        $parent->refresh();
        expect($parent->edition_number)->toBe(1);
    });

    it('increments edition_number for subsequent editions', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $parent = createProject($brand, ['name' => 'Piano Base', 'edition_number' => 1]);

        // Create edition 2
        createProject($brand, [
            'name' => 'Ed 2',
            'parent_project_id' => $parent->id,
            'edition_number' => 2,
        ]);

        // Create edition 3 via API
        $response = $this->actingAs($user)->postJson("/api/projects/{$parent->id}/editions", [
            'name' => 'Ed 3',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]);

        $response->assertStatus(201);
        expect($response->json('edition_number'))->toBe(3);
    });

    it('inherits platforms, brief, and personas from parent', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $parent = createProject($brand, [
            'platforms' => ['instagram', 'linkedin'],
            'brief' => 'Il brief originale',
            'buyer_personas' => ['personas' => [['name' => 'Test']]],
        ]);

        $response = $this->actingAs($user)->postJson("/api/projects/{$parent->id}/editions", [
            'name' => 'Nuova edizione',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $response->assertStatus(201);
        expect($response->json('platforms'))->toBe(['instagram', 'linkedin']);
        expect($response->json('brief'))->toBe('Il brief originale');
        expect($response->json('buyer_personas.personas.0.name'))->toBe('Test');
    });

    it('validates required fields', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $parent = createProject($brand);

        $response = $this->actingAs($user)->postJson("/api/projects/{$parent->id}/editions", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'start_date', 'end_date']);
    });

    it('returns 404 for project from another organization', function () {
        [$userA, $orgA] = createAuthenticatedUser();
        [$userB, $orgB] = createAuthenticatedUser([
            'email' => 'userB@test.com',
        ], [
            'name' => 'Org B',
            'slug' => 'org-b-' . \Illuminate\Support\Str::random(5),
            'email' => 'orgB@test.com',
        ]);

        $brand = createBrand($orgA);
        $parent = createProject($brand);

        $response = $this->actingAs($userB)->postJson("/api/projects/{$parent->id}/editions", [
            'name' => 'Hacked Edition',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $response->assertNotFound();
    });
});

describe('GET /api/projects (edition filtering)', function () {
    it('hides child editions from the main list by default', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $parent = createProject($brand, ['name' => 'Parent', 'edition_number' => 1]);
        createProject($brand, [
            'name' => 'Child',
            'parent_project_id' => $parent->id,
            'edition_number' => 2,
        ]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertOk();
        expect($response->json())->toHaveCount(1);
        expect($response->json('0.name'))->toBe('Parent');
    });

    it('includes editions in nested array for parent projects', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $parent = createProject($brand, ['name' => 'Parent', 'edition_number' => 1]);
        createProject($brand, [
            'name' => 'Child Ed',
            'parent_project_id' => $parent->id,
            'edition_number' => 2,
        ]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertOk();
        expect($response->json('0.editions'))->toHaveCount(1);
        expect($response->json('0.editions.0.name'))->toBe('Child Ed');
    });

    it('shows all projects when include_editions=1', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $parent = createProject($brand, ['name' => 'Parent', 'edition_number' => 1]);
        createProject($brand, [
            'name' => 'Child',
            'parent_project_id' => $parent->id,
            'edition_number' => 2,
        ]);

        $response = $this->actingAs($user)->getJson('/api/projects?include_editions=1');

        $response->assertOk();
        expect($response->json())->toHaveCount(2);
    });
});

describe('GET /api/projects/{id}/history-context', function () {
    it('returns empty context for non-edition project', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/history-context");

        $response->assertOk();
        expect($response->json('has_history'))->toBeFalse();
        expect($response->json('history_context'))->toBe('');
    });

    it('returns history context for edition with previous editions', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $parent = createProject($brand, ['name' => 'Parent', 'edition_number' => 1]);
        $ed1child = createProject($brand, [
            'name' => 'Ed 1',
            'parent_project_id' => $parent->id,
            'edition_number' => 2,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
        ]);

        // Add some posts to ed1child
        createPost($ed1child, [
            'content' => 'Post di test per edizione precedente',
            'platform' => 'instagram',
            'scheduled_date' => '2026-03-15',
        ]);

        $ed2 = createProject($brand, [
            'name' => 'Ed 2',
            'parent_project_id' => $parent->id,
            'edition_number' => 3,
        ]);

        $response = $this->actingAs($user)->getJson("/api/projects/{$ed2->id}/history-context");

        $response->assertOk();
        expect($response->json('has_history'))->toBeTrue();
        expect($response->json('history_context'))->toContain('STORICO EDIZIONI PRECEDENTI');
        expect($response->json('history_context'))->toContain('Post di test');
    });
});
