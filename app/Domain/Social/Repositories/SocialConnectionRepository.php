<?php

declare(strict_types=1);

namespace App\Domain\Social\Repositories;

use App\Domain\Post\Enums\Platform;
use App\Domain\Social\Contracts\SocialConnectionRepositoryInterface;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Database\Eloquent\Collection;

final class SocialConnectionRepository implements SocialConnectionRepositoryInterface
{
    public function __construct(
        private readonly SocialConnection $model,
    ) {}

    public function findActiveByBrand(int $brandId): Collection
    {
        return $this->model
            ->where('brand_id', $brandId)
            ->where('is_active', true)
            ->orderBy('platform')
            ->get();
    }

    public function findById(int $id): ?SocialConnection
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): SocialConnection
    {
        return $this->model->findOrFail($id);
    }

    public function findByBrandAndPlatform(int $brandId, Platform $platform): ?SocialConnection
    {
        return $this->model
            ->where('brand_id', $brandId)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();
    }

    public function create(array $data): SocialConnection
    {
        return $this->model->create($data);
    }

    public function update(SocialConnection $connection, array $data): SocialConnection
    {
        $connection->update($data);

        return $connection->refresh();
    }

    public function disconnect(SocialConnection $connection): bool
    {
        return $connection->update(['is_active' => false]);
    }

    public function findWithExpiringTokens(\DateTimeInterface $before): Collection
    {
        return $this->model
            ->where('is_active', true)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', $before)
            ->get();
    }
}
