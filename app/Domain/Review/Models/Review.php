<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Enums\Platform;
use App\Domain\Review\Enums\MarketingOpportunity;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Enums\Sentiment;
use App\Domain\Review\Enums\Urgency;
use App\Domain\Shared\Traits\BelongsToOrganization;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        // ── Scoring (M2) ─────────────────────────────────
        'sentiment',
        'urgency',
        'topics',
        'is_fake_suspect',
        'marketing_opportunity',
        'scoring_rationale',
        'scored_by_model',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'rating'                => 'int',
            'raw_payload'           => 'array',
            'review_created_at'     => 'datetime',
            'review_updated_at'     => 'datetime',
            'fetched_at'            => 'datetime',
            'status'                => ReviewStatus::class,
            'platform'              => Platform::class,
            // ── Scoring (M2) ─────────────────────────────
            'sentiment'             => Sentiment::class,
            'urgency'               => Urgency::class,
            'topics'                => 'array',
            'is_fake_suspect'       => 'boolean',
            'marketing_opportunity' => MarketingOpportunity::class,
            'scored_at'             => 'datetime',
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

    public function replies(): HasMany
    {
        return $this->hasMany(ReviewReply::class);
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(ReviewReply::class)->latestOfMany('id');
    }

    public function activeDraft(): HasOne
    {
        return $this->hasOne(ReviewReply::class)
            ->where('status', \App\Domain\Review\Enums\ReplyStatus::Draft->value)
            ->latestOfMany('id');
    }

    public function sentReply(): HasOne
    {
        return $this->hasOne(ReviewReply::class)
            ->where('status', \App\Domain\Review\Enums\ReplyStatus::Sent->value)
            ->latestOfMany('id');
    }

    public function getHasSentReplyAttribute(): bool
    {
        return $this->sentReply !== null;
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

    public function scopeScored(Builder $q): Builder
    {
        return $q->whereNotNull('scored_at');
    }

    public function scopeUnscored(Builder $q): Builder
    {
        return $q->whereNull('scored_at');
    }

    public function scopeRequiresAttention(Builder $q): Builder
    {
        return $q->where(function (Builder $w): void {
            $w->where('urgency', Urgency::High->value)
                ->orWhere(function (Builder $ww): void {
                    $ww->where('sentiment', Sentiment::Negative->value)
                        ->whereIn('status', [ReviewStatus::New->value, ReviewStatus::Scored->value]);
                })
                ->orWhere('is_fake_suspect', true);
        });
    }
}
