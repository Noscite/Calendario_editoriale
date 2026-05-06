<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use App\Domain\User\Models\User;

/**
 * Auth API tests — replica esatta del flow Python OAuth2PasswordRequestForm.
 * Il login usa form-urlencoded con campo "username" (che contiene l'email).
 */

describe('POST /api/auth/register', function () {
    it('registers a new user', function () {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'newuser@test.com',
            'password' => 'SecurePass123',
            'full_name' => 'Test User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'email', 'full_name', 'is_active', 'created_at']);

        expect($response->json('email'))->toBe('newuser@test.com');
    });

    it('rejects duplicate email', function () {
        User::create([
            'email' => 'existing@test.com',
            'password' => 'password',
            'full_name' => 'Existing',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'existing@test.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(400)
            ->assertJson(['detail' => 'Email già registrata']);
    });
});

describe('POST /api/auth/login', function () {
    it('logs in with form-urlencoded username+password (Python-compatible)', function () {
        [$user] = createAuthenticatedUser(['email' => 'login@test.com', 'password' => 'testpass123']);

        // ATTENZIONE: il login Python usa form-urlencoded con "username" e "password"
        $response = $this->post('/api/auth/login', [
            'username' => 'login@test.com',
            'password' => 'testpass123',
        ], ['Content-Type' => 'application/x-www-form-urlencoded']);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type']);

        expect($response->json('token_type'))->toBe('bearer');
        expect($response->json('access_token'))->not->toBeEmpty();
    });

    it('also works with JSON body', function () {
        [$user] = createAuthenticatedUser(['email' => 'jsonlogin@test.com', 'password' => 'testpass']);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'jsonlogin@test.com',
            'password' => 'testpass',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type']);
    });

    it('rejects wrong password', function () {
        [$user] = createAuthenticatedUser(['email' => 'bad@test.com', 'password' => 'correct']);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'bad@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)
            ->assertJson(['detail' => 'Email o password non corretti']);
    });

    it('rejects non-existent user', function () {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'nobody@test.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
    });
});

describe('GET /api/auth/me', function () {
    it('returns authenticated user info', function () {
        [$user, $org] = createAuthenticatedUser([
            'email' => 'me@test.com',
            'full_name' => 'John Doe',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'id', 'email', 'full_name', 'role', 'is_active',
                'organization_id', 'organization_name',
            ]);

        expect($response->json('email'))->toBe('me@test.com');
        expect($response->json('full_name'))->toBe('John Doe');
        expect($response->json('organization_name'))->toBe('Test Org');
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    });
});

describe('PUT /api/auth/profile', function () {
    it('updates profile fields', function () {
        [$user] = createAuthenticatedUser();

        $response = $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'full_name' => 'Updated Name',
                'phone' => '+39123456789',
                'company' => 'Noscite Srl',
                'city' => 'Milano',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Profilo aggiornato']);

        $user->refresh();
        expect($user->full_name)->toBe('Updated Name');
        expect($user->phone)->toBe('+39123456789');
    });
});

describe('POST /api/auth/change-password', function () {
    it('changes password when current password is correct', function () {
        [$user] = createAuthenticatedUser(['password' => 'oldpass123']);

        $response = $this->actingAs($user)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'oldpass123',
                'new_password' => 'newpass456',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Password cambiata con successo']);
    });

    it('rejects wrong current password', function () {
        [$user] = createAuthenticatedUser(['password' => 'correct']);

        $response = $this->actingAs($user)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'wrong',
                'new_password' => 'newpass456',
            ]);

        $response->assertStatus(400)
            ->assertJson(['detail' => 'Password attuale non corretta']);
    });
});

describe('POST /api/auth/refresh', function () {
    it('refreshes the token', function () {
        [$user] = createAuthenticatedUser();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type']);

        // Old token should be revoked
        expect($response->json('access_token'))->not->toBe($token);
    });
});
