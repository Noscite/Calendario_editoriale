<?php

declare(strict_types=1);

namespace App\Domain\AiUsage\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'brand_id',
        'project_id',
        'purpose',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'cache_creation_tokens',
        'cache_read_tokens',
        'cost_usd',
        'cost_eur',
        'created_at',
    ];

    protected $casts = [
        'cost_usd'   => 'float',
        'cost_eur'   => 'float',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
