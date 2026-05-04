<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Enums\Platform;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Shared\Traits\BelongsToOrganization;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'social_connection_id',
        'brand_id',
        'platform',
        'external_review_id',
        'reviewer_name',
        'reviewer_photo_url',
        'rating',
        'comment',
        'language',
        'review_created_at',
        'review_updated_at',
        'fetched_at',
        'status',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'rating'             => 'int',
            'raw_payload'        => 'array',
            'review_created_at'  => 'datetime',
            'review_updated_at'  => 'datetime',
            'fetched_at'         => 'datetime',
            'status'             => ReviewStatus::class,
            'platform'           => Platform::class,
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function socialConnection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeUnreplied(Builder $q): Builder
    {
        return $q->whereNotIn('status', [ReviewStatus::Replied->value, ReviewStatus::Ignored->value]);
    }

    public function scopeNegative(Builder $q): Builder
    {
        return $q->where('rating', '<=', 2);
    }

    public function scopePositive(Builder $q): Builder
    {
        return $q->where('rating', '>=', 4);
    }
}
