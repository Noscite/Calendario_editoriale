<?php

namespace App\Domain\Project\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Shared\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'brand_id',
        'name',
        'start_date',
        'end_date',
        'platforms',
        'posts_per_week',
        'themes',
        'brief',
        'custom_prompt',
        'status',
        // Nuovi campi
        'reference_urls',
        'target_audience',
        'objectives',
        'content_pillars',
        'competitors',
        'special_dates',
        'buyer_personas',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'platforms' => 'json',
            'posts_per_week' => 'json',
            'themes' => 'json',
            'status' => ProjectStatus::class,
            'reference_urls' => 'json',
            'objectives' => 'json',
            'content_pillars' => 'json',
            'competitors' => 'json',
            'special_dates' => 'json',
            'buyer_personas' => 'json',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
