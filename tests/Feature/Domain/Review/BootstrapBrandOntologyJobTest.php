<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use App\Domain\Review\Jobs\BootstrapBrandOntologyJob;
use App\Domain\Review\Contracts\OntologyBootstrapServiceInterface;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    // L'observer su Brand dispatcha BootstrapBrandOntologyJob su create:
    // ma qui vogliamo testare il JOB in isolamento, quindi fakeQueue.
    Queue::fake();
});

function createBrandForJobTest(array $attrs = []): Brand
{
    $org = Organization::create([
        'name'             => 'Job Test Org',
        'slug'             => 'job-test-org-' . Str::random(8),
        'email'            => 'job-' . Str::random(6) . '@test.com',
        'is_system_tenant' => false,
        'is_active'        => true,
    ]);

    return Brand::withoutGlobalScope('organization')->create(array_merge([
        'organization_id' => $org->id,
        'name'            => 'Job Test Brand ' . uniqid(),
    ], $attrs));
}

it('calls bootstrap service for eligible brand', function () {
    $brand = createBrandForJobTest([
        'sector'          => 'ristorazione',
        'description'     => str_repeat('a', 60),
        'review_ontology' => null,
    ]);

    $mock = Mockery::mock(OntologyBootstrapServiceInterface::class);
    $mock->shouldReceive('bootstrapForBrand')
        ->once()
        ->with(Mockery::on(fn ($b) => $b instanceof Brand && $b->id === $brand->id))
        ->andReturn([['id' => 'food', 'label' => 'Cibo', 'description' => '']]);

    (new BootstrapBrandOntologyJob($brand->id))->handle($mock);
});

it('skips when ontology already populated', function () {
    $brand = createBrandForJobTest([
        'sector'          => 'ristorazione',
        'description'     => str_repeat('a', 60),
        'review_ontology' => [['id' => 'x', 'label' => 'X', 'description' => '']],
    ]);

    $mock = Mockery::mock(OntologyBootstrapServiceInterface::class);
    $mock->shouldNotReceive('bootstrapForBrand');

    (new BootstrapBrandOntologyJob($brand->id))->handle($mock);
});

it('skips when brand does not exist', function () {
    $mock = Mockery::mock(OntologyBootstrapServiceInterface::class);
    $mock->shouldNotReceive('bootstrapForBrand');

    (new BootstrapBrandOntologyJob(99999))->handle($mock);
});
