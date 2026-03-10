<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Domain\User\Contracts\UserServiceInterface;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Registra un nuovo utente, con check duplicato su email.
     *
     * Replica /register di auth.py.
     */
    public function register(array $data): User
    {
        if ($this->userRepository->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['Email già registrata.'],
            ]);
        }

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;
        $data['role'] ??= 'editor';

        return $this->userRepository->create($data);
    }

    /**
     * Autentica un utente via email/password e genera un token Sanctum.
     *
     * Replica /login di auth.py (JWT → Sanctum).
     *
     * @return array{user: User, token: string}
     */
    public function authenticate(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenziali non valide.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Account disattivato. Contattare l\'amministratore.'],
            ]);
        }

        // Revoca token precedenti e ne crea uno nuovo
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Aggiorna i campi del profilo utente.
     *
     * Replica /profile PUT di auth.py.
     */
    public function updateProfile(int $userId, array $data): User
    {
        $user = $this->userRepository->findOrFail($userId);

        // Impedisci aggiornamento di campi sensibili via profile
        $allowed = [
            'full_name', 'phone', 'company', 'address',
            'city', 'country', 'vat_number', 'notes',
        ];

        $filtered = array_intersect_key($data, array_flip($allowed));

        return $this->userRepository->update($user, $filtered);
    }

    /**
     * Cambia password dopo verifica della corrente. Min 8 caratteri.
     *
     * Replica /change-password di auth.py.
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->userRepository->findOrFail($userId);

        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password corrente non valida.'],
            ]);
        }

        if (strlen($newPassword) < 8) {
            throw ValidationException::withMessages([
                'new_password' => ['La nuova password deve contenere almeno 8 caratteri.'],
            ]);
        }

        $this->userRepository->update($user, [
            'password' => Hash::make($newPassword),
        ]);
    }

    /**
     * Rinnova il token Sanctum.
     *
     * Replica /refresh di auth.py (in Sanctum: revoca + ricrea).
     */
    public function refreshToken(string $token): string
    {
        // In Sanctum non esiste un vero refresh. Si revoca e ricrea.
        // Il controller passerà il token dall'header; qui lavoriamo sull'utente autenticato.
        // Per semplicità: il controller chiamerà questo metodo passando il token corrente,
        // ma l'utente sarà già autenticato dal middleware Sanctum.
        // La logica effettiva è nel controller; qui funge da segnaposto.
        return $token;
    }

    public function delete(int $userId): void
    {
        $user = $this->userRepository->findOrFail($userId);

        // Revoca tutti i token prima del soft delete
        $user->tokens()->delete();

        $this->userRepository->delete($user);
    }

    /**
     * Ottieni il profilo dell'utente con relazione organizzazione.
     *
     * Replica /me di auth.py.
     */
    public function getProfile(int $userId): User
    {
        $user = $this->userRepository->findOrFail($userId);

        $user->load('organization');

        return $user;
    }
}
