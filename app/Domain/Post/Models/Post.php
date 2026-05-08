<?php

namespace App\Domain\Post\Models;

use App\Domain\Post\Enums\ContentType;
use App\Domain\Post\Enums\Platform;
use App\Domain\Post\Enums\PostStatus;
use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Traits\BelongsToOrganization;
use App\Domain\Social\Models\PostPublication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'project_id',
        'platform',
        'scheduled_date',
        'scheduled_time',
        'title',
        'content',
        'hashtags',
        'pillar',
        'post_type',
        'visual_prompt',
        'visual_suggestion',
        'call_to_action',
        'cta',
        'image_prompt',
        'image_url',
        'media_type',
        'image_format',
        'carousel_images',
        'carousel_prompts',
        'is_carousel',
        'content_type',
        'status',
        'publication_status',
        'generation_metadata',
    ];

    protected $attributes = [
        'media_type' => 'image',
        'image_format' => '1080x1080',
        'is_carousel' => false,
        'content_type' => 'post',
        'status' => 'draft',
        'publication_status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'scheduled_date' => 'date',
            'hashtags' => 'json',
            'post_type' => 'string',
            'content_type' => ContentType::class,
            'carousel_images' => 'json',
            'carousel_prompts' => 'json',
            'is_carousel' => 'boolean',
            'status' => PostStatus::class,
            'publication_status' => PublicationStatus::class,
            'generation_metadata' => 'array',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(PostPublication::class);
    }
}
