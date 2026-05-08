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
        'parent_project_id',
        'edition_number',
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
        // ── Wizard PR-2 (preparati ora, popolati in PR successiva) ─
        'personas_source',
        'personas_ai_suggestion',
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
            'personas_ai_suggestion' => 'array',
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

    // ── Edition relations ─────────────────────────────────────

    public function parentProject(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_project_id');
    }

    public function editions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_project_id')->orderBy('edition_number');
    }

    // ── Edition helpers ───────────────────────────────────────

    public function isEdition(): bool
    {
        return $this->parent_project_id !== null;
    }

    public function isParent(): bool
    {
        return ! $this->isEdition() && $this->editions()->exists();
    }

    /**
     * Returns the next edition number for this parent project.
     */
    public function nextEditionNumber(): int
    {
        $maxChild = (int) $this->editions()->max('edition_number');
        $own = (int) $this->edition_number;

        return max($maxChild, $own) + 1;
    }
}
