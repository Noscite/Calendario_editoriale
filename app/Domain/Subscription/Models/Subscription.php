<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\User\Models\User;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    // ── Stati ─────────────────────────────────────────────────────

    public const STATUS_TRIAL           = 'trial';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_ACTIVE          = 'active';
    public const STATUS_EXPIRED         = 'expired';
    public const STATUS_CANCELLED       = 'cancelled';

    public const VALID_PLAN_NAMES = ['small', 'standard', 'pro', 'unlimited'];

    // ── Eloquent ───────────────────────────────────────────────────

    protected $fillable = [
        'organization_id',
        'status',
        'trial_started_at',
        'trial_ends_at',
        'paid_period_starts_at',
        'paid_period_ends_at',
        'paid_period_months',
        'activated_by_admin_id',
        'activated_at',
        'payment_reference',
        'payment_notes',
        'trial_tokens_consumed',
        'trial_calendars_generated',
    ];

    protected function casts(): array
    {
        return [
            'trial_started_at'      => 'datetime',
            'trial_ends_at'         => 'datetime',
            'paid_period_starts_at' => 'datetime',
            'paid_period_ends_at'   => 'datetime',
            'activated_at'          => 'datetime',
            'paid_period_months'    => 'integer',
            'trial_tokens_consumed' => 'integer',
            'trial_calendars_generated' => 'integer',
        ];
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    // ── Relations ──────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_admin_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeInTrial(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_TRIAL)
            ->where('trial_ends_at', '>', now());
    }

    public function scopeTrialExpiring(\Illuminate\Database\Eloquent\Builder $query, int $days): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_TRIAL)
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)]);
    }

    public function scopePendingPayment(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_PENDING_PAYMENT);
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('paid_period_ends_at', '>', now());
    }

    public function scopeExpired(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere(function ($q2) {
                  $q2->where('status', self::STATUS_ACTIVE)
                     ->where('paid_period_ends_at', '<', now());
              });
        });
    }

    // ── Metodi di stato ────────────────────────────────────────────

    public function isInTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function isTrialExpired(): bool
    {
        return $this->status === self::STATUS_TRIAL
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->paid_period_ends_at !== null
            && $this->paid_period_ends_at->isFuture();
    }

    public function canAccessApp(): bool
    {
        if ($this->organization?->is_system_tenant) {
            return true;
        }

        return $this->isInTrial() || $this->isActive();
    }

    public function canUseFeature(string $feature): bool
    {
        if ($this->isActive()) {
            return true;
        }

        if ($this->isInTrial()) {
            $disabledDuringTrial = config('trial.features_disabled_during_trial', []);
            return ! in_array($feature, $disabledDuringTrial, strict: true);
        }

        return false;
    }

    public function daysLeftInTrial(): int
    {
        if ($this->trial_ends_at === null || ! $this->isInTrial()) {
            return 0;
        }

        return (int) max(0, now()->diffInDays($this->trial_ends_at, absolute: false));
    }

    public function daysLeftInPaidPeriod(): int
    {
        if ($this->paid_period_ends_at === null || ! $this->isActive()) {
            return 0;
        }

        return (int) max(0, now()->diffInDays($this->paid_period_ends_at, absolute: false));
    }
}
