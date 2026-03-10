<?php

namespace App\Domain\Social\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Enums\Platform;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialConnection extends Model
{
    use HasFactory;

    const CREATED_AT = 'connected_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'brand_id',
        'platform',
        // Token OAuth
        'access_token',
        'refresh_token',
        'token_expires_at',
        // Info account esterno
        'external_account_id',
        'external_account_name',
        'external_account_url',
        'account_type',
        // Metadata
        'is_active',
        'connected_by_user_id',
        'last_used_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(PostPublication::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SocialMetric::class);
    }
}
