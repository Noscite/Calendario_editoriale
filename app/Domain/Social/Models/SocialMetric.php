<?php

namespace App\Domain\Social\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMetric extends Model
{
    use HasFactory;

    const UPDATED_AT = null;
    const CREATED_AT = 'fetched_at';

    protected $fillable = [
        'social_connection_id',
        'post_publication_id',
        // Metriche comuni
        'impressions',
        'reach',
        'engagement',
        'likes',
        'comments',
        'shares',
        'clicks',
        'saves',
        // Metriche account
        'followers_count',
        'followers_gained',
        // Periodo
        'metric_date',
        'metric_type',
        'raw_data',
    ];

    protected $attributes = [
        'impressions' => 0,
        'reach' => 0,
        'engagement' => 0,
        'likes' => 0,
        'comments' => 0,
        'shares' => 0,
        'clicks' => 0,
        'saves' => 0,
        'metric_type' => 'daily',
    ];

    protected function casts(): array
    {
        return [
            'impressions' => 'integer',
            'reach' => 'integer',
            'engagement' => 'integer',
            'likes' => 'integer',
            'comments' => 'integer',
            'shares' => 'integer',
            'clicks' => 'integer',
            'saves' => 'integer',
            'followers_count' => 'integer',
            'followers_gained' => 'integer',
            'metric_date' => 'datetime',
            'fetched_at' => 'datetime',
            'raw_data' => 'json',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function socialConnection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class);
    }

    public function postPublication(): BelongsTo
    {
        return $this->belongsTo(PostPublication::class);
    }
}
