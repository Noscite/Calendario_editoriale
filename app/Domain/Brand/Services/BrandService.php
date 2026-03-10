<?php

declare(strict_types=1);

namespace App\Domain\Brand\Services;

use App\Domain\Brand\Contracts\BrandRepositoryInterface;
use App\Domain\Brand\Contracts\BrandServiceInterface;
use App\Domain\Brand\Models\Brand;
use App\Domain\Subscription\Contracts\BillingServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class BrandService implements BrandServiceInterface
{
    public function __construct(
        private readonly BrandRepositoryInterface $brandRepository,
        private readonly BillingServiceInterface $billing,
    ) {}

    public function listForOrganization(int $organizationId): Collection
    {
        return $this->brandRepository->findByOrganizationWithCounts($organizationId);
    }

    public function getById(int $id): Brand
    {
        return $this->brandRepository->findOrFail($id);
    }

    public function create(int $organizationId, array $data): Brand
    {
        // Verifica limiti piano
        if (! $this->billing->canCreateBrand($organizationId)) {
            throw ValidationException::withMessages([
                'brand' => ['Limite massimo di brand raggiunto per il piano corrente. Effettua un upgrade.'],
            ]);
        }

        $data['organization_id'] = $organizationId;

        return $this->brandRepository->create($data);
    }

    public function update(int $brandId, array $data): Brand
    {
        $brand = $this->brandRepository->findOrFail($brandId);

        return $this->brandRepository->update($brand, $data);
    }

    public function delete(int $brandId): void
    {
        $brand = $this->brandRepository->findOrFail($brandId);

        $this->brandRepository->delete($brand);
    }
}
