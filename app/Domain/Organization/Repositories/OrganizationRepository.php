<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Contracts\OrganizationRepositoryInterface;
use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        private readonly Organization $model,
    ) {}

    public function findById(int $id): ?Organization
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Organization
    {
        return $this->model->findOrFail($id);
    }

    public function findBySlug(string $slug): ?Organization
    {
        return $this->model->where('slug', $slug)->first();
    }

    public function listPaginated(
        ?string $search = null,
        ?int $planId = null,
        ?OrganizationStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $this->model->newQuery();

        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($planId !== null) {
            $query->where('plan_id', $planId);
        }

        if ($status !== null) {
            $query->where('subscription_status', $status);
        }

        return $query
            ->with('plan')
            ->withCount(['users', 'brands'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): Organization
    {
        return $this->model->create($data);
    }

    public function update(Organization $organization, array $data): Organization
    {
        $organization->update($data);

        return $organization->refresh();
    }

    public function delete(Organization $organization): bool
    {
        return (bool) $organization->delete();
    }

    public function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while ($this->model->where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
