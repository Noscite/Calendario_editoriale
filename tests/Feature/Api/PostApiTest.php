<?php

declare(strict_types=1);

use App\Domain\Post\Models\Post;

describe('GET /api/posts/project/{project_id}', function () {
    it('lists posts for a project', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);
        createPost($project, ['title' => 'Post 1']);
        createPost($project, ['title' => 'Post 2']);

        $response = $this->actingAs($user)->getJson("/api/posts/project/{$project->id}");

        $response->assertOk();
        expect($response->json())->toHaveCount(2);
    });

    it('returns empty for project with no posts', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)->getJson("/api/posts/project/{$project->id}");

        $response->assertOk()
            ->assertJsonCount(0);
    });
});

describe('GET /api/posts/{id}', function () {
    it('shows a single post', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);
        $post = createPost($project, ['content' => 'Hello World']);

        $response = $this->actingAs($user)->getJson("/api/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id', 'project_id', 'platform', 'scheduled_date',
                'content', 'status', 'publication_status',
            ])
            ->assertJsonFragment(['content' => 'Hello World']);
    });
});

describe('POST /api/posts/manual', function () {
    it('creates a manual post', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)->postJson('/api/posts/manual', [
            'project_id' => $project->id,
            'platform' => 'instagram',
            'scheduled_date' => '2026-03-15',
            'content' => 'Contenuto manuale',
            'hashtags' => ['#test', '#laravel'],
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => 'Contenuto manuale']);

        expect($response->json('status'))->toBe('draft');
    });

    it('validates required fields', function () {
        [$user] = createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson('/api/posts/manual', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'platform', 'scheduled_date', 'content']);
    });
});

describe('PUT /api/posts/{id}', function () {
    it('updates post content', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);
        $post = createPost($project, ['content' => 'Old']);

        $response = $this->actingAs($user)->putJson("/api/posts/{$post->id}", [
            'content' => 'Updated content',
            'title' => 'New Title',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['content' => 'Updated content']);
    });
});

describe('PATCH /api/posts/{id}', function () {
    it('partially updates a post', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);
        $post = createPost($project, ['title' => 'Original']);

        $response = $this->actingAs($user)->patchJson("/api/posts/{$post->id}", [
            'title' => 'Patched',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Patched']);
    });
});

describe('DELETE /api/posts/{id}', function () {
    it('deletes a post', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);
        $post = createPost($project);

        $response = $this->actingAs($user)->deleteJson("/api/posts/{$post->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Post eliminato']);

        expect(Post::find($post->id))->toBeNull();
    });
});

describe('POST /api/posts/batch-delete', function () {
    it('batch deletes multiple posts', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);
        $p1 = createPost($project, ['title' => 'D1']);
        $p2 = createPost($project, ['title' => 'D2']);
        $p3 = createPost($project, ['title' => 'Keep']);

        $response = $this->actingAs($user)->postJson('/api/posts/batch-delete', [
            'post_ids' => [$p1->id, $p2->id],
        ]);

        $response->assertOk()
            ->assertJsonFragment(['deleted_count' => 2]);

        expect(Post::find($p1->id))->toBeNull();
        expect(Post::find($p2->id))->toBeNull();
        expect(Post::find($p3->id))->not->toBeNull();
    });

    it('validates post_ids is required', function () {
        [$user] = createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson('/api/posts/batch-delete', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['post_ids']);
    });
});

describe('POST /api/posts/check-overlap/{project_id}', function () {
    it('detects overlapping posts', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);
        createPost($project, ['scheduled_date' => '2026-03-10', 'platform' => 'instagram']);
        createPost($project, ['scheduled_date' => '2026-03-15', 'platform' => 'linkedin']);
        createPost($project, ['scheduled_date' => '2026-04-15', 'platform' => 'instagram']);

        $response = $this->actingAs($user)->postJson("/api/posts/check-overlap/{$project->id}", [
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['has_overlap' => true])
            ->assertJsonFragment(['overlapping_count' => 2]);
    });

    it('reports no overlap when range is clean', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);
        $project = createProject($brand);
        createPost($project, ['scheduled_date' => '2026-01-10']);

        $response = $this->actingAs($user)->postJson("/api/posts/check-overlap/{$project->id}", [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['has_overlap' => false]);
    });
});
