<?php

namespace App\Domain\Organization\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\UsageLog;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'vat_number',
        'address',
        // Subscription
        'plan_id',
        'subscription_status',
        'trial_ends_at',
        'subscription_starts_at',
        'subscription_ends_at',
        // Stripe
        'stripe_customer_id',
        'stripe_subscription_id',
        // Enterprise custom
        'custom_limits',
        'notes',
        'api_key_hash',
        'is_active',
        'is_system_tenant',
        // Vertical defaults (inherited at brand creation)
        'default_vertical',
        'default_territory_meta',
    ];

    protected function casts(): array
    {
        return [
            'subscription_status' => OrganizationStatus::class,
            'trial_ends_at' => 'datetime',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'custom_limits' => 'json',
            'is_active'        => 'boolean',
            'is_system_tenant' => 'boolean',
            'default_territory_meta' => 'array',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
