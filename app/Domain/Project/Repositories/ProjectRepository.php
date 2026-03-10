<?php

declare(strict_types=1);

namespace App\Domain\Project\Repositories;

use App\Domain\Project\Contracts\ProjectRepositoryInterface;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;

final class ProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(
        private readonly Project $model,
    ) {}

    public function findByBrand(int $brandId): Collection
    {
        return $this->model
            ->where('brand_id', $brandId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id): ?Project
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Project
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Project
    {
        return $this->model->create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->refresh();
    }

    public function updateStatus(Project $project, ProjectStatus $status): Project
    {
        $project->update(['status' => $status]);

        return $project->refresh();
    }

    public function delete(Project $project): bool
    {
        return (bool) $project->delete();
    }

    public function findByStatus(ProjectStatus $status): Collection
    {
        return $this->model
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->get();
    }
}
