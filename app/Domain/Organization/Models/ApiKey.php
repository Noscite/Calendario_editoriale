<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API Key per autenticazione Public API v1.
 *
 * Corrisponde a api_key.py (Python).
 */
class ApiKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'key_hash',
        'key_prefix',
        'scopes',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes'       => 'json',
            'is_active'    => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    // ── Helpers ────────────────────────────────────────────────

    /**
     * Hash di una API key raw con SHA-256 (stessa logica Python).
     */
    public static function hashKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    /**
     * Verifica se questa key possiede lo scope richiesto.
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        if (in_array('admin', $scopes, true)) {
            return true;
        }

        return in_array($scope, $scopes, true);
    }

    // ── Relations ──────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
