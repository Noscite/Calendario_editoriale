<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
});

function createCleanupOrg(string $name = 'Test Org', bool $systemTenant = false): Organization
{
    return Organization::create([
        'name'             => $name,
        'slug'             => Str::slug($name) . '-' . Str::random(6),
        'email'            => 'org-' . Str::random(6) . '@test.com',
        'is_system_tenant' => $systemTenant,
        'is_active'        => true,
    ]);
}

function createCleanupUser(Organization $org, ?string $email = null, string $role = 'admin'): User
{
    return User::create([
        'organization_id' => $org->id,
        'email'           => $email ?? 'user_' . uniqid() . '@example.com',
        'full_name'       => 'Test User',
        'password'        => bcrypt('test'),
        'role'            => $role,
        'is_active'       => true,
    ]);
}

it('lists test orgs and users in dry run', function () {
    $org = createCleanupOrg('Test Org X');
    createCleanupUser($org);

    $this->artisan('kalendarium:cleanup-test-orgs-users')
        ->assertSuccessful()
        ->expectsOutputToContain('Candidate organizations: 1')
        ->expectsOutputToContain('Candidate users: 1')
        ->expectsOutputToContain('DRY RUN');

    expect(Organization::find($org->id))->not->toBeNull();
});

it('protects system tenant', function () {
    $sysOrg = createCleanupOrg('Test Org System', systemTenant: true);

    $this->artisan('kalendarium:cleanup-test-orgs-users')
        ->assertSuccessful()
        ->expectsOutputToContain('Nessun residuo di test trovato');

    expect(Organization::find($sysOrg->id))->not->toBeNull();
});

it('protects superusers', function () {
    $org       = createCleanupOrg('Test Org Z');
    $superuser = createCleanupUser($org, 'super@example.com', 'superuser');

    $this->artisan('kalendarium:cleanup-test-orgs-users --force')
        ->expectsConfirmation(
            'Confermi cancellazione DEFINITIVA?',
            'yes',
        )
        ->assertSuccessful();

    expect(User::find($superuser->id))->not->toBeNull();
});

it('protects orgs with real brands', function () {
    $org  = createCleanupOrg('Test Org Y');
    $user = createCleanupUser($org);

    Brand::withoutGlobalScope('organization')->create([
        'organization_id' => $org->id,
        'name'            => 'Pizzeria Reale Mario',
        'sector'          => 'ristorazione',
    ]);

    $this->artisan('kalendarium:cleanup-test-orgs-users')
        ->assertSuccessful()
        ->expectsOutputToContain('Nessun residuo di test trovato');

    expect(Organization::find($org->id))->not->toBeNull();
    expect(User::find($user->id))->not->toBeNull();
});

it('deletes test orgs and users with force', function () {
    $org1 = createCleanupOrg('Test Org A');
    $u1   = createCleanupUser($org1, 'a@example.com');
    $org2 = createCleanupOrg('Test Org B');
    $u2   = createCleanupUser($org2, 'b@example.com');

    $this->artisan('kalendarium:cleanup-test-orgs-users --force')
        ->expectsConfirmation(
            'Confermi cancellazione DEFINITIVA?',
            'yes',
        )
        ->expectsOutputToContain('Eliminati')
        ->assertSuccessful();

    expect(Organization::find($org1->id))->toBeNull();
    expect(Organization::find($org2->id))->toBeNull();
    expect(User::find($u1->id))->toBeNull();
    expect(User::find($u2->id))->toBeNull();
});
