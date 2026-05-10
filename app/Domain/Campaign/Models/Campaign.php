<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Post\Models\Post;
use App\Domain\Shared\Traits\BelongsToOrganization;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'brief',
        'status',
        'start_date',
        'end_date',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status'     => CampaignStatus::class,
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    protected static function newFactory(): \Database\Factories\CampaignFactory
    {
        return \Database\Factories\CampaignFactory::new();
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_campaign')->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Helper per verificare se la campagna è in uno stato che la fa contare per il plan limit.
     */
    public function isActiveStatus(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Verifica che le date siano coerenti (start <= end).
     */
    public function hasValidDateRange(): bool
    {
        return $this->start_date && $this->end_date && $this->start_date->lessThanOrEqualTo($this->end_date);
    }
}
