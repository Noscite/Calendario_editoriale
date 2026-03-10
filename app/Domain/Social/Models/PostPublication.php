<?php

namespace App\Domain\Social\Models;

use App\Domain\Post\Models\Post;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostPublication extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'social_connection_id',
        // Stato pubblicazione
        'status',
        'scheduled_for',
        'published_at',
        // Riferimento esterno
        'external_post_id',
        'external_post_url',
        // Errori
        'error_message',
        'retry_count',
        'last_retry_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'retry_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'retry_count' => 'integer',
            'last_retry_at' => 'datetime',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialConnection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SocialMetric::class);
    }
}
