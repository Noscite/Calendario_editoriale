<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use App\Domain\Review\Jobs\BootstrapBrandOntologyJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function createBrandForObserverTest(array $attrs = []): Brand
{
    $org = Organization::query()
        ->withoutGlobalScope('organization')
        ->where('is_system_tenant', true)
        ->first();

    if ($org === null) {
        $org = Organization::create([
            'name'             => 'Test Org Observer',
            'slug'             => 'test-org-observer-' . Str::random(8),
            'email'            => 'observer-' . Str::random(6) . '@test.com',
            'is_system_tenant' => false,
            'is_active'        => true,
        ]);
    }

    return Brand::withoutGlobalScope('organization')->create(array_merge([
        'organization_id' => $org->id,
        'name'            => 'Test Brand ' . uniqid(),
    ], $attrs));
}

it('dispatches bootstrap when brand created with complete data', function () {
    Queue::fake();

    createBrandForObserverTest([
        'sector'      => 'ristorazione',
        'description' => str_repeat('a', 60),
    ]);

    Queue::assertPushed(BootstrapBrandOntologyJob::class);
});

it('does not dispatch when sector missing', function () {
    Queue::fake();

    createBrandForObserverTest([
        'sector'      => '',
        'description' => str_repeat('a', 60),
    ]);

    Queue::assertNotPushed(BootstrapBrandOntologyJob::class);
});

it('does not dispatch when description too short', function () {
    Queue::fake();

    createBrandForObserverTest([
        'sector'      => 'ristorazione',
        'description' => 'troppo corta',
    ]);

    Queue::assertNotPushed(BootstrapBrandOntologyJob::class);
});

it('does not dispatch when ontology already exists', function () {
    Queue::fake();

    createBrandForObserverTest([
        'sector'          => 'ristorazione',
        'description'     => str_repeat('a', 60),
        'review_ontology' => [['id' => 'pizza', 'label' => 'Pizza']],
    ]);

    Queue::assertNotPushed(BootstrapBrandOntologyJob::class);
});

it('redispatches when sector changes', function () {
    Queue::fake();

    $brand = createBrandForObserverTest([
        'sector'          => 'ristorazione',
        'description'     => str_repeat('a', 60),
        'review_ontology' => [['id' => 'pizza', 'label' => 'Pizza']],
    ]);

    Queue::fake(); // reset

    $brand->update(['sector' => 'beauty']);

    Queue::assertPushed(BootstrapBrandOntologyJob::class);
    expect($brand->fresh()->review_ontology)->toBeNull();
});

it('does not redispatch when description changes only', function () {
    Queue::fake();

    $brand = createBrandForObserverTest([
        'sector'          => 'ristorazione',
        'description'     => str_repeat('a', 60),
        'review_ontology' => [['id' => 'pizza', 'label' => 'Pizza']],
    ]);

    Queue::fake();

    $brand->update(['description' => str_repeat('b', 80)]);

    Queue::assertNotPushed(BootstrapBrandOntologyJob::class);
});
