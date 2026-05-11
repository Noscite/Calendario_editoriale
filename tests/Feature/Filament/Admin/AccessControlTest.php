<?php

declare(strict_types=1);

use App\Domain\User\Models\User;

it('superuser can access /filament-admin', function () {
    [$user] = createAuthenticatedUser(['role' => 'superuser']);

    $response = $this->actingAs($user)->get('/filament-admin');

    // Filament panel root redirect to login page works on guest; on auth
    // user it serves the dashboard (200) o redirige al primo resource (302).
    expect($response->getStatusCode())->toBeIn([200, 302]);

    // Sanity check: canAccessPanel returns true
    $panel = \Filament\Facades\Filament::getPanel('filament-admin');
    expect($user->canAccessPanel($panel))->toBeTrue();
});

it('admin role (org-level admin) cannot access /filament-admin', function () {
    [$user] = createAuthenticatedUser(['role' => 'admin']);

    $panel = \Filament\Facades\Filament::getPanel('filament-admin');
    expect($user->canAccessPanel($panel))->toBeFalse();
});

it('editor role cannot access /filament-admin', function () {
    [$user] = createAuthenticatedUser(['role' => 'editor']);

    $panel = \Filament\Facades\Filament::getPanel('filament-admin');
    expect($user->canAccessPanel($panel))->toBeFalse();
});

it('guest cannot access /filament-admin (401 or 302 to login)', function () {
    $response = $this->get('/filament-admin');
    // Filament può rispondere 302 (redirect to login) o 401 (Unauthorized)
    // a seconda della configurazione del middleware. Entrambe negano l'accesso.
    expect($response->getStatusCode())->toBeIn([302, 401]);
});
