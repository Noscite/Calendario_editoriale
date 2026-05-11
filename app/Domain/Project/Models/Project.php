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

    /**
     * Le campagne lanciate dentro questo calendario (via QuickAddPostModal
     * tab "Campagna AI"). Derivate dai posts.campaign_id, distinct.
     */
    public function campaigns()
    {
        return \App\Domain\Campaign\Models\Campaign::query()
            ->whereIn('id', $this->posts()->whereNotNull('campaign_id')->distinct()->pluck('campaign_id'));
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

    /**
     * Normalizza content_pillars a list<string>, accettando entrambi gli shape:
     *  - array di stringhe (legacy / shape canonica per Project)
     *  - array di {name, description?} objects (shape erronea introdotta dal
     *    primo rollout di EditProjectWizardV2; vedi hotfix d6afb82+)
     *
     * Usato da ProjectController::toDict (response GET) e da GenerateCalendarJob
     * (input PromptBuilder/matchPillar) come single source of truth per
     * defensive auto-healing dei record DB sporchi.
     *
     * @return list<string>
     */
    public static function normalizeContentPillarsList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $p) {
            if (is_string($p)) {
                $trimmed = trim($p);
                if ($trimmed !== '') $out[] = $trimmed;
            } elseif (is_array($p)) {
                $name = trim((string) ($p['name'] ?? ''));
                if ($name !== '') $out[] = $name;
            } elseif (is_object($p)) {
                $name = trim((string) ($p->name ?? ''));
                if ($name !== '') $out[] = $name;
            }
        }
        return $out;
    }
}
