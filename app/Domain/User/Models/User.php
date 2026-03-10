<?php

namespace App\Domain\User\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\Shared\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use BelongsToOrganization, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'email',
        'password',
        'hashed_password',
        'full_name',
        'is_active',
        'role',
        'organization_id',
        // Profilo esteso
        'phone',
        'company',
        'address',
        'city',
        'country',
        'vat_number',
        'notes',
        // Microsoft/Azure AD
        'azure_id',
        'auth_provider',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // Sync hashed_password from password for Python compatibility
            if (empty($user->hashed_password) && ! empty($user->password)) {
                $user->hashed_password = $user->password;
            }
        });

        static::updating(function (User $user) {
            if ($user->isDirty('password') && ! $user->isDirty('hashed_password')) {
                $user->hashed_password = $user->password;
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => 'editor',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // ── Password mapping (DB usa "hashed_password", Laravel si aspetta "password") ──

    /**
     * Legge la password da hashed_password (colonna reale nel DB Python).
     * Converte $2b$ → $2y$ per compatibilità PHP 8.4.
     */
    public function getPasswordAttribute(): ?string
    {
        $hash = $this->attributes['hashed_password'] ?? null;
        return $hash ? str_replace('$2b$', '$2y$', $hash) : null;
    }

    /**
     * Quando si scrive "password", salva in hashed_password.
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['hashed_password'] = $value;
    }

    /**
     * Usato da Laravel Auth per il controllo password.
     * Converte $2b$ → $2y$ per compatibilità PHP 8.4 con hash Python/passlib.
     */
    public function getAuthPassword(): string
    {
        $hash = $this->hashed_password ?? '';
        // passlib usa $2b$, PHP usa $2y$ — funzionalmente identici
        return str_replace('$2b$', '$2y$', $hash);
    }

    // ── Accessors ──────────────────────────────────────────────

    public function getIsSuperuserAttribute(): bool
    {
        return $this->role === 'superuser';
    }

    public function getIsAdminAttribute(): bool
    {
        return in_array($this->role, ['superuser', 'admin']);
    }
}
