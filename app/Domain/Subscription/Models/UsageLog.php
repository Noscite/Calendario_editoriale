<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use App\Domain\Shared\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLog extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $table = 'usage_tracking';

    protected $fillable = [
        'organization_id',
        'brand_id',
        'period_start',
        'period_end',
        // Contatori
        'calendar_generations_used',
        'text_tokens_used',
        'images_generated',
        'replies_sent',
        // Overage
        'overage_tokens',
        'overage_images',
        'overage_cost',
    ];

    protected $attributes = [
        'calendar_generations_used' => 0,
        'text_tokens_used' => 0,
        'images_generated' => 0,
        'replies_sent' => 0,
        'overage_tokens' => 0,
        'overage_images' => 0,
        'overage_cost' => 0,
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'calendar_generations_used' => 'integer',
            'text_tokens_used' => 'integer',
            'images_generated' => 'integer',
            'replies_sent' => 'integer',
            'overage_tokens' => 'integer',
            'overage_images' => 'integer',
            'overage_cost' => 'decimal:2',
        ];
    }

    // ── Relations ──────────────────────────────────────────────
    // organization() is provided by BelongsToOrganization trait

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
