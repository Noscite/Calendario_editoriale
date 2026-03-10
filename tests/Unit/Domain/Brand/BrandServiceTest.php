<?php

declare(strict_types=1);

use App\Domain\Brand\Contracts\BrandRepositoryInterface;
use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandService;
use App\Domain\Subscription\Contracts\BillingServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->brandRepo = Mockery::mock(BrandRepositoryInterface::class);
    $this->billing = Mockery::mock(BillingServiceInterface::class);
    $this->service = new BrandService($this->brandRepo, $this->billing);
});

afterEach(fn () => Mockery::close());

describe('listForOrganization', function () {
    it('returns brands with counts for an organization', function () {
        $brands = new Collection([new Brand(['name' => 'B1']), new Brand(['name' => 'B2'])]);

        $this->brandRepo
            ->shouldReceive('findByOrganizationWithCounts')
            ->with(1)
            ->once()
            ->andReturn($brands);

        $result = $this->service->listForOrganization(1);

        expect($result)->toHaveCount(2);
    });
});

describe('getById', function () {
    it('returns a brand by id', function () {
        $brand = new Brand(['name' => 'Test Brand']);

        $this->brandRepo
            ->shouldReceive('findOrFail')
            ->with(42)
            ->once()
            ->andReturn($brand);

        $result = $this->service->getById(42);

        expect($result->name)->toBe('Test Brand');
    });
});

describe('create', function () {
    it('creates a brand when billing allows', function () {
        $this->billing
            ->shouldReceive('canCreateBrand')
            ->with(1)
            ->once()
            ->andReturn(true);

        $brand = new Brand(['name' => 'New Brand', 'organization_id' => 1]);

        $this->brandRepo
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['organization_id'] === 1 && $data['name'] === 'New Brand')
            ->andReturn($brand);

        $result = $this->service->create(1, ['name' => 'New Brand']);

        expect($result->name)->toBe('New Brand');
        expect($result->organization_id)->toBe(1);
    });

    it('throws ValidationException when brand limit reached', function () {
        $this->billing
            ->shouldReceive('canCreateBrand')
            ->with(1)
            ->once()
            ->andReturn(false);

        $this->service->create(1, ['name' => 'Over Limit']);
    })->throws(ValidationException::class);
});

describe('update', function () {
    it('updates an existing brand', function () {
        $brand = new Brand(['name' => 'Old Name']);
        $updatedBrand = new Brand(['name' => 'New Name']);

        $this->brandRepo
            ->shouldReceive('findOrFail')
            ->with(5)
            ->once()
            ->andReturn($brand);

        $this->brandRepo
            ->shouldReceive('update')
            ->with($brand, ['name' => 'New Name'])
            ->once()
            ->andReturn($updatedBrand);

        $result = $this->service->update(5, ['name' => 'New Name']);

        expect($result->name)->toBe('New Name');
    });
});

describe('delete', function () {
    it('deletes a brand', function () {
        $brand = new Brand(['name' => 'To Delete']);

        $this->brandRepo
            ->shouldReceive('findOrFail')
            ->with(10)
            ->once()
            ->andReturn($brand);

        $this->brandRepo
            ->shouldReceive('delete')
            ->with($brand)
            ->once()
            ->andReturn(true);

        $this->service->delete(10);

        // No exception means success
        expect(true)->toBeTrue();
    });
});
