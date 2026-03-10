<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model,
    ) {}

    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): User
    {
        return $this->model->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function findByOrganization(int $organizationId): Collection
    {
        return $this->model
            ->withoutOrganization()
            ->where('organization_id', $organizationId)
            ->orderBy('full_name')
            ->get();
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }
}
