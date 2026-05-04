<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'display_name',
        'price_monthly',
        'price_yearly',
        // Limiti
        'max_brands',
        'max_users',
        'monthly_calendar_generations',
        'monthly_reply_count',
        'monthly_text_tokens',
        'monthly_images',
        // Features
        'has_export_excel',
        'has_activity_log',
        'has_advanced_roles',
        'has_api_access',
        'has_crm_integration',
        'has_auto_publishing',
        'has_analytics',
        'has_ab_testing',
        'has_own_api_keys',
        // Overage
        'allows_overage',
        'overage_price_per_1k_tokens',
        'overage_price_per_image',
        'is_active',
    ];

    protected $attributes = [
        'max_brands' => 1,
        'max_users' => 1,
        'monthly_calendar_generations' => 3,
        'monthly_text_tokens' => 50000,
        'monthly_images' => 20,
        'has_export_excel' => false,
        'has_activity_log' => false,
        'has_advanced_roles' => false,
        'has_api_access' => false,
        'has_crm_integration' => false,
        'has_auto_publishing' => false,
        'has_analytics' => false,
        'has_ab_testing'  => false,
        'has_own_api_keys' => false,
        'allows_overage'  => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'max_brands' => 'integer',
            'max_users' => 'integer',
            'monthly_calendar_generations' => 'integer',
            'monthly_text_tokens' => 'integer',
            'monthly_images' => 'integer',
            'has_export_excel' => 'boolean',
            'has_activity_log' => 'boolean',
            'has_advanced_roles' => 'boolean',
            'has_api_access' => 'boolean',
            'has_crm_integration' => 'boolean',
            'has_auto_publishing' => 'boolean',
            'has_analytics' => 'boolean',
            'has_ab_testing'  => 'boolean',
            'has_own_api_keys' => 'boolean',
            'allows_overage'  => 'boolean',
            'overage_price_per_1k_tokens' => 'decimal:4',
            'overage_price_per_image' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'plan_id');
    }
}
