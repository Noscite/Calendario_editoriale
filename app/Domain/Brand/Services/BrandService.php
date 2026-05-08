<?php

declare(strict_types=1);

namespace App\Domain\Brand\Services;

use App\Domain\Brand\Contracts\BrandRepositoryInterface;
use App\Domain\Brand\Contracts\BrandServiceInterface;
use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Support\PillarNameNormalizer;
use App\Domain\Subscription\Contracts\BillingServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class BrandService implements BrandServiceInterface
{
    private const MAX_DEFAULT_PILLARS = 6;

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

    public function mergeDefaultPillars(Brand $brand, array $newPillars): array
    {
        $existing = is_array($brand->default_content_pillars) ? $brand->default_content_pillars : [];

        // Indicizza pillar esistenti per nome normalizzato per dedup O(1)
        $existingByNorm = [];
        foreach ($existing as $idx => $pillar) {
            $name = is_array($pillar) ? ($pillar['name'] ?? null) : null;
            $norm = PillarNameNormalizer::normalize($name);
            if ($norm !== '') {
                $existingByNorm[$norm] = $idx;
            }
        }

        $merged            = $existing;
        $skippedDuplicates = 0;
        $addedCount        = 0;

        foreach ($newPillars as $candidate) {
            $name = is_array($candidate) ? trim((string) ($candidate['name'] ?? '')) : '';
            $description = is_array($candidate) ? trim((string) ($candidate['description'] ?? '')) : '';

            $norm = PillarNameNormalizer::normalize($name);
            if ($norm === '') {
                continue;
            }

            if (array_key_exists($norm, $existingByNorm)) {
                $skippedDuplicates++;
                continue;
            }

            $merged[] = [
                'name'        => $name,
                'description' => $description,
            ];
            $existingByNorm[$norm] = count($merged) - 1;
            $addedCount++;
        }

        // FIFO drop su overflow: rimuovi i più vecchi (testa array)
        $droppedCount = 0;
        if (count($merged) > self::MAX_DEFAULT_PILLARS) {
            $droppedCount = count($merged) - self::MAX_DEFAULT_PILLARS;
            $merged       = array_slice($merged, $droppedCount);
        }

        // Persisti solo se cambia qualcosa (idempotenza pure on identical input)
        if ($addedCount > 0 || $droppedCount > 0) {
            $this->brandRepository->update($brand, ['default_content_pillars' => $merged]);
        }

        return [
            'pillars'            => $merged,
            'added_count'        => $addedCount,
            'dropped_count'      => $droppedCount,
            'skipped_duplicates' => $skippedDuplicates,
        ];
    }
}
