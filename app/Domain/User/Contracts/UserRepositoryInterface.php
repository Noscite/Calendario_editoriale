<?php

declare(strict_types=1);

namespace App\Domain\User\Contracts;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Trova un utente per ID.
     */
    public function findById(int $id): ?User;

    /**
     * Trova un utente per ID o lancia eccezione.
     */
    public function findOrFail(int $id): User;

    /**
     * Trova un utente per email.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Trova tutti gli utenti di un'organizzazione.
     */
    public function findByOrganization(int $organizationId): Collection;

    /**
     * Crea un nuovo utente.
     */
    public function create(array $data): User;

    /**
     * Aggiorna un utente esistente.
     */
    public function update(User $user, array $data): User;

    /**
     * Elimina un utente (soft delete).
     */
    public function delete(User $user): bool;
}
