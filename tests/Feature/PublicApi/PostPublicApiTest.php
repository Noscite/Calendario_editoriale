<?php

declare(strict_types=1);

use App\Domain\Organization\Models\ApiKey;
use App\Domain\Post\Models\Post;

/**
 * Public API test via X-API-Key.
 * Routes: /api/v1/posts (read scope), /api/v1/posts (write scope), etc.
 */

beforeEach(function () {
    [$this->user, $this->org] = createAuthenticatedUser();
    $this->brand = createBrand($this->org);
    $this->project = createProject($this->brand);
});

describe('GET /api/v1/posts (read scope)', function () {
    it('lists posts via API key', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['read']);
        createPost($this->project, ['title' => 'API Post 1']);
        createPost($this->project, ['title' => 'API Post 2']);

        $response = $this->getJson('/api/v1/posts', ['X-API-Key' => $rawKey]);

        $response->assertOk();
        expect($response->json())->toHaveCount(2);
    });

    it('filters by project_id', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['read']);
        $project2 = createProject($this->brand, ['name' => 'P2']);
        createPost($this->project, ['title' => 'P1 Post']);
        createPost($project2, ['title' => 'P2 Post']);

        $response = $this->getJson("/api/v1/posts?project_id={$this->project->id}", [
            'X-API-Key' => $rawKey,
        ]);

        $response->assertOk();
        expect($response->json())->toHaveCount(1);
        expect($response->json('0.title'))->toBe('P1 Post');
    });

    it('filters by platform', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['read']);
        createPost($this->project, ['platform' => 'instagram']);
        createPost($this->project, ['platform' => 'linkedin']);

        $response = $this->getJson('/api/v1/posts?platform=instagram', [
            'X-API-Key' => $rawKey,
        ]);

        $response->assertOk();
        expect($response->json())->toHaveCount(1);
    });

    it('rejects without API key', function () {
        $this->getJson('/api/v1/posts')->assertStatus(401);
    });

    it('rejects invalid API key', function () {
        $this->getJson('/api/v1/posts', ['X-API-Key' => 'invalid_key'])
            ->assertStatus(401);
    });

    it('rejects expired API key', function () {
        $rawKey = 'nc_expired_' . \Illuminate\Support\Str::random(32);
        ApiKey::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'name' => 'Expired',
            'key_hash' => ApiKey::hashKey($rawKey),
            'key_prefix' => substr($rawKey, 0, 8),
            'scopes' => ['read'],
            'is_active' => true,
            'expires_at' => now()->subDay(),
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/posts', ['X-API-Key' => $rawKey])
            ->assertStatus(401)
            ->assertJsonFragment(['detail' => 'API Key scaduta']);
    });
});

describe('GET /api/v1/posts/{id} (read scope)', function () {
    it('shows a single post', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['read']);
        $post = createPost($this->project, ['content' => 'API content']);

        $response = $this->getJson("/api/v1/posts/{$post->id}", ['X-API-Key' => $rawKey]);

        $response->assertOk()
            ->assertJsonFragment(['content' => 'API content']);
    });

    it('returns 404 for post in other org', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['read']);

        $otherOrg = \App\Domain\Organization\Models\Organization::create([
            'name' => 'Other', 'slug' => 'other-' . \Illuminate\Support\Str::random(6), 'email' => 'other-' . \Illuminate\Support\Str::random(6) . '@test.com',
            'subscription_status' => 'active', 'is_active' => true,
        ]);
        $otherBrand = \App\Domain\Brand\Models\Brand::withoutGlobalScope('organization')->create([
            'organization_id' => $otherOrg->id, 'name' => 'OB',
        ]);
        $otherProject = \App\Domain\Project\Models\Project::withoutGlobalScope('organization')->create([
            'organization_id' => $otherOrg->id,
            'brand_id' => $otherBrand->id, 'name' => 'OP',
            'start_date' => now(), 'end_date' => now()->addMonth(),
        ]);
        $otherPost = Post::withoutGlobalScope('organization')->create([
            'organization_id' => $otherOrg->id,
            'project_id' => $otherProject->id, 'platform' => 'instagram',
            'scheduled_date' => now(), 'content' => 'secret',
        ]);

        $response = $this->getJson("/api/v1/posts/{$otherPost->id}", ['X-API-Key' => $rawKey]);

        $response->assertStatus(404);
    });
});

describe('POST /api/v1/posts (write scope)', function () {
    it('creates a post via API key', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['write']);

        $response = $this->postJson('/api/v1/posts', [
            'project_id' => $this->project->id,
            'platform' => 'linkedin',
            'scheduled_date' => '2026-04-01',
            'content' => 'API created post',
        ], ['X-API-Key' => $rawKey]);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => 'API created post']);
    });

    it('rejects with read-only key', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['read']);

        $response = $this->postJson('/api/v1/posts', [
            'project_id' => $this->project->id,
            'platform' => 'instagram',
            'scheduled_date' => '2026-04-01',
            'content' => 'Should fail',
        ], ['X-API-Key' => $rawKey]);

        $response->assertStatus(403);
    });
});

describe('POST /api/v1/posts/bulk (write scope)', function () {
    it('bulk creates multiple posts', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['write']);

        $response = $this->postJson('/api/v1/posts/bulk', [
            'posts' => [
                [
                    'project_id' => $this->project->id,
                    'platform' => 'instagram',
                    'scheduled_date' => '2026-04-01',
                    'content' => 'Bulk post 1',
                ],
                [
                    'project_id' => $this->project->id,
                    'platform' => 'linkedin',
                    'scheduled_date' => '2026-04-02',
                    'content' => 'Bulk post 2',
                ],
            ],
        ], ['X-API-Key' => $rawKey]);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true, 'created_count' => 2]);

        expect($response->json('post_ids'))->toHaveCount(2);
    });
});

describe('PATCH /api/v1/posts/{id} (write scope)', function () {
    it('updates a post via API key', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['write']);
        $post = createPost($this->project, ['content' => 'Original']);

        $response = $this->patchJson("/api/v1/posts/{$post->id}", [
            'content' => 'Updated via API',
        ], ['X-API-Key' => $rawKey]);

        $response->assertOk()
            ->assertJsonFragment(['content' => 'Updated via API']);
    });
});

describe('DELETE /api/v1/posts/{id} (write scope)', function () {
    it('deletes a post via API key', function () {
        [$apiKey, $rawKey] = createApiKey($this->org, $this->user, ['write']);
        $post = createPost($this->project);

        $response = $this->deleteJson("/api/v1/posts/{$post->id}", [], ['X-API-Key' => $rawKey]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);

        expect(Post::find($post->id))->toBeNull();
    });
});
