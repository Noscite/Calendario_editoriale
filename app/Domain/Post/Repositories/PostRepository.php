<?php

declare(strict_types=1);

namespace App\Domain\Post\Repositories;

use App\Domain\Post\Contracts\PostRepositoryInterface;
use App\Domain\Post\Models\Post;
use Illuminate\Database\Eloquent\Collection;

final class PostRepository implements PostRepositoryInterface
{
    public function __construct(
        private readonly Post $model,
    ) {}

    public function findByProject(int $projectId): Collection
    {
        return $this->model
            ->where('project_id', $projectId)
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();
    }

    public function findById(int $id): ?Post
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Post
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Post
    {
        return $this->model->create($data);
    }

    public function createMany(array $posts): Collection
    {
        $created = new Collection();

        foreach ($posts as $postData) {
            $created->push($this->model->create($postData));
        }

        return $created;
    }

    public function update(Post $post, array $data): Post
    {
        $post->update($data);

        return $post->refresh();
    }

    public function delete(Post $post): bool
    {
        return (bool) $post->delete();
    }

    public function deleteMany(array $postIds): int
    {
        return $this->model
            ->whereIn('id', $postIds)
            ->delete();
    }

    public function findScheduledForPublishing(): Collection
    {
        return $this->model
            ->where('publication_status', 'scheduled')
            ->where('scheduled_date', '<=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();
    }

    public function findByProjectAndDateRange(
        int $projectId,
        string $startDate,
        string $endDate,
    ): Collection {
        return $this->model
            ->where('project_id', $projectId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();
    }

    public function countOverlapping(
        int $projectId,
        string $startDate,
        string $endDate,
    ): int {
        return $this->model
            ->where('project_id', $projectId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->count();
    }
}
