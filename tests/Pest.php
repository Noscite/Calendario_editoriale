<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

use App\Domain\User\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Brand\Models\Brand;
use App\Domain\Project\Models\Project;
use App\Domain\Post\Models\Post;
use App\Domain\Organization\Models\ApiKey;

/**
 * Crea un piano di default per i test.
 */
function createPlan(array $overrides = []): Plan
{
    $name = $overrides['name'] ?? 'test';

    return Plan::firstOrCreate(['name' => $name], array_merge([
        'name' => $name,
        'display_name' => 'Test Plan',
        'price_monthly' => 29.00,
        'price_yearly' => 290.00,
        'max_brands' => 5,
        'max_users' => 5,
        'monthly_calendar_generations' => 10,
        'monthly_text_tokens' => 100_000,
        'monthly_images' => 50,
        'is_active' => true,
        'allows_overage' => false,
    ], $overrides));
}

/**
 * Crea org + user autenticato. Ritorna [User, Organization].
 */
function createAuthenticatedUser(array $userOverrides = [], array $orgOverrides = []): array
{
    $plan = createPlan();

    $orgEmail  = $orgOverrides['email']  ?? 'org-'  . \Illuminate\Support\Str::random(8) . '@test.com';
    $userEmail = $userOverrides['email'] ?? 'user-' . \Illuminate\Support\Str::random(8) . '@test.com';

    $org = Organization::create(array_merge([
        'name' => 'Test Org',
        'slug' => 'test-org-' . \Illuminate\Support\Str::random(8),
        'email' => $orgEmail,
        'plan_id' => $plan->id,
        'subscription_status' => 'active',
        'is_active' => true,
    ], $orgOverrides));

    $user = User::create(array_merge([
        'email' => $userEmail,
        'password' => 'password',
        'full_name' => 'Test User',
        'organization_id' => $org->id,
        'role' => 'admin',
        'is_active' => true,
    ], $userOverrides));

    return [$user, $org];
}

/**
 * Crea brand per test.
 */
function createBrand(Organization $org, array $overrides = []): Brand
{
    return Brand::withoutGlobalScope('organization')->create(array_merge([
        'organization_id' => $org->id,
        'name' => 'Test Brand',
        'sector' => 'Tech',
        'tone_of_voice' => 'professionale',
    ], $overrides));
}

/**
 * Crea progetto per test.
 */
function createProject(Brand $brand, array $overrides = []): Project
{
    return Project::withoutGlobalScope('organization')->create(array_merge([
        'organization_id' => $brand->organization_id,
        'brand_id' => $brand->id,
        'name' => 'Test Project',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'platforms' => ['instagram', 'linkedin'],
        'status' => 'draft',
    ], $overrides));
}

/**
 * Crea post per test.
 */
function createPost(Project $project, array $overrides = []): Post
{
    return Post::withoutGlobalScope('organization')->create(array_merge([
        'organization_id' => $project->organization_id,
        'project_id' => $project->id,
        'platform' => 'instagram',
        'scheduled_date' => now()->addDays(3)->toDateString(),
        'scheduled_time' => '09:00',
        'content' => 'Test post content',
        'title' => 'Test Post',
        'status' => 'draft',
        'publication_status' => 'draft',
    ], $overrides));
}

/**
 * Crea API key per test Public API. Ritorna [ApiKey, raw_key].
 */
function createApiKey(Organization $org, User $user, array $scopes = ['read', 'write']): array
{
    $rawKey = 'nc_' . \Illuminate\Support\Str::random(4) . '_' . \Illuminate\Support\Str::random(28);
    $apiKey = ApiKey::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'name' => 'Test Key ' . \Illuminate\Support\Str::random(4),
        'key_hash' => ApiKey::hashKey($rawKey),
        'key_prefix' => substr($rawKey, 0, 8),
        'scopes' => $scopes,
        'is_active' => true,
        'created_at' => now(),
    ]);

    return [$apiKey, $rawKey];
}
