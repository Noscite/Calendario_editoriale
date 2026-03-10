<?php

declare(strict_types=1);

namespace App\Domain\Social\Contracts;

use App\Domain\Post\Enums\Platform;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Database\Eloquent\Collection;

interface SocialConnectionRepositoryInterface
{
    /**
     * Trova tutte le connessioni attive di un brand.
     */
    public function findActiveByBrand(int $brandId): Collection;

    /**
     * Trova una connessione per ID.
     */
    public function findById(int $id): ?SocialConnection;

    /**
     * Trova una connessione per ID o lancia eccezione.
     */
    public function findOrFail(int $id): SocialConnection;

    /**
     * Trova una connessione per brand e piattaforma.
     */
    public function findByBrandAndPlatform(int $brandId, Platform $platform): ?SocialConnection;

    /**
     * Crea una nuova connessione social.
     */
    public function create(array $data): SocialConnection;

    /**
     * Aggiorna una connessione social.
     */
    public function update(SocialConnection $connection, array $data): SocialConnection;

    /**
     * Disconnetti (soft-delete: is_active = false).
     */
    public function disconnect(SocialConnection $connection): bool;

    /**
     * Trova connessioni con token in scadenza.
     */
    public function findWithExpiringTokens(\DateTimeInterface $before): Collection;
}
