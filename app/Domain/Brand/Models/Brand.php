<?php

namespace App\Domain\Brand\Models;

use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Document\Models\BrandDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Traits\BelongsToOrganization;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'sector',
        'target_audience',
        'tone_of_voice',
        'ai_profile',
        'brand_values',
        'website',
        'website_url',
        'linkedin_url',
        'instagram_url',
        'facebook_url',
        'unique_selling_points',
        'colors',
        'style_guide',
        'voice_examples',
        'review_fetch_interval_minutes',
        'review_ontology',
        // ── Auto-reply (M4) ────────────────────────────────
        'auto_reply_enabled',
        'auto_reply_min_rating',
        'auto_reply_only_positive_sentiment',
        'auto_reply_tone',
        'auto_reply_review_mode',
        'auto_reply_delay_minutes',
        // ── Wizard PR-1 ────────────────────────────────────
        'tagline',
        'founder',
        'narrative_assets',
        'default_content_pillars',
        'forbidden_topics',
        // ── Territorial vertical (UNPLI / Pro Loco) ────────
        'vertical',
        'territory_meta',
    ];

    protected function casts(): array
    {
        return [
            'brand_values'                       => 'json',
            'voice_examples'                     => 'array',
            'review_fetch_interval_minutes'      => 'integer',
            'review_ontology'                    => 'array',
            // ── Auto-reply (M4) ───────────────────────────
            'auto_reply_enabled'                 => 'boolean',
            'auto_reply_min_rating'              => 'integer',
            'auto_reply_only_positive_sentiment' => 'boolean',
            'auto_reply_review_mode'             => 'boolean',
            'auto_reply_delay_minutes'           => 'integer',
            // ── Wizard PR-1 ───────────────────────────────
            'founder'                            => 'array',
            'narrative_assets'                   => 'array',
            'default_content_pillars'            => 'array',
            'forbidden_topics'                   => 'array',
            // ── Territorial vertical ──────────────────────
            'territory_meta'                     => 'array',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function socialConnections(): HasMany
    {
        return $this->hasMany(SocialConnection::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BrandDocument::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(BrandApiKey::class);
    }

    public function campaigns(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'brand_campaign')->withTimestamps();
    }

    public function getApiKey(string $keyName): ?string
    {
        return $this->apiKeys
            ->firstWhere('key_name', $keyName)
            ?->encrypted_value;
    }
}
