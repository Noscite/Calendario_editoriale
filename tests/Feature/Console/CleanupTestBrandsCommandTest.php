<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('lists test brands in dry run mode', function () {
    [, $org] = createAuthenticatedUser();
    createBrand($org, ['name' => 'Test Brand 1']);
    createBrand($org, ['name' => 'Brand Test']);
    createBrand($org, ['name' => 'Real Brand']);

    $this->artisan('kalendarium:cleanup-test-brands')
        ->assertSuccessful()
        ->expectsOutputToContain('Trovati 2 brand candidati')
        ->expectsOutputToContain('DRY RUN');

    expect(Brand::count())->toBe(3);
});

it('does nothing when no test brands exist', function () {
    [, $org] = createAuthenticatedUser();
    createBrand($org, ['name' => 'Real Brand A']);
    createBrand($org, ['name' => 'Real Brand B']);

    $this->artisan('kalendarium:cleanup-test-brands --force')
        ->assertSuccessful()
        ->expectsOutputToContain('Nessun brand test trovato');
});

it('deletes test brands with force and confirmation', function () {
    [, $org] = createAuthenticatedUser();
    createBrand($org, ['name' => 'Test Brand 1']);
    createBrand($org, ['name' => 'Test Brand 2']);
    createBrand($org, ['name' => 'Real Brand']);

    $this->artisan('kalendarium:cleanup-test-brands --force')
        ->expectsConfirmation(
            'Confermi la cancellazione DEFINITIVA di questi brand e tutte le entity correlate?',
            'yes',
        )
        ->assertSuccessful()
        ->expectsOutputToContain('Eliminati 2 brand di test');

    expect(Brand::count())->toBe(1);
    expect(Brand::first()->name)->toBe('Real Brand');
});

it('aborts when user does not confirm', function () {
    [, $org] = createAuthenticatedUser();
    createBrand($org, ['name' => 'Test Brand 1']);

    $this->artisan('kalendarium:cleanup-test-brands --force')
        ->expectsConfirmation(
            'Confermi la cancellazione DEFINITIVA di questi brand e tutte le entity correlate?',
            'no',
        )
        ->assertSuccessful()
        ->expectsOutputToContain('Annullato');

    expect(Brand::count())->toBe(1);
});
