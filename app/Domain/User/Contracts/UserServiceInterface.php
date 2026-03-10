<?php

declare(strict_types=1);

namespace App\Domain\User\Contracts;

use App\Domain\User\Models\User;

interface UserServiceInterface
{
    /**
     * Registra un nuovo utente.
     */
    public function register(array $data): User;

    /**
     * Autentica un utente e genera un JWT/token.
     *
     * @return array{user: User, token: string}
     */
    public function authenticate(string $email, string $password): array;

    /**
     * Aggiorna il profilo di un utente.
     */
    public function updateProfile(int $userId, array $data): User;

    /**
     * Cambia la password di un utente.
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): void;

    /**
     * Rinnova il token di accesso.
     */
    public function refreshToken(string $token): string;

    /**
     * Elimina un utente (soft delete).
     */
    public function delete(int $userId): void;

    /**
     * Ottieni il profilo dell'utente corrente con info organizzazione.
     */
    public function getProfile(int $userId): User;
}
