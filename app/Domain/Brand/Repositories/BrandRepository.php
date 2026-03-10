<?php

declare(strict_types=1);

namespace App\Domain\Brand\Repositories;

use App\Domain\Brand\Contracts\BrandRepositoryInterface;
use App\Domain\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

final class BrandRepository implements BrandRepositoryInterface
{
    public function __construct(
        private readonly Brand $model,
    ) {}

    public function findByOrganization(int $organizationId): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();
    }

    public function findByOrganizationWithCounts(int $organizationId): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->withCount(['projects', 'projects as posts_count' => function ($query) {
                $query->join('posts', 'projects.id', '=', 'posts.project_id')
                    ->select(\DB::raw('count(posts.id)'));
            }])
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?Brand
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Brand
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Brand
    {
        return $this->model->create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand->refresh();
    }

    public function delete(Brand $brand): bool
    {
        return (bool) $brand->delete();
    }
}
