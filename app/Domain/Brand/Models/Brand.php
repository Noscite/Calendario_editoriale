<?php

namespace App\Domain\Brand\Models;

use App\Domain\Document\Models\BrandDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Traits\BelongsToOrganization;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Brand\Models\BrandApiKey;
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
    ];

    protected function casts(): array
    {
        return [
            'brand_values' => 'json',
            'voice_examples' => 'array',
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

    public function getApiKey(string $keyName): ?string
    {
        return $this->apiKeys
            ->firstWhere('key_name', $keyName)
            ?->encrypted_value;
    }
}
