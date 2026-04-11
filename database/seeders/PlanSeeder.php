<?php

namespace Database\Seeders;

use App\Domain\Subscription\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'                         => 'small',
                'display_name'                 => 'Small',
                'price_monthly'                => 29.00,
                'price_yearly'                 => 290.00,
                'max_brands'                   => 1,
                'max_users'                    => 1,
                'monthly_calendar_generations' => 5,
                'monthly_text_tokens'          => 100000,
                'monthly_images'               => 15,
                'has_export_excel'             => false,
                'has_activity_log'             => false,
                'has_advanced_roles'           => false,
                'has_api_access'               => false,
                'has_crm_integration'          => false,
                'has_auto_publishing'          => false,
                'has_analytics'                => false,
                'has_ab_testing'               => false,
                'has_own_api_keys'             => false,
                'allows_overage'               => false,
                'overage_price_per_1k_tokens'  => null,
                'overage_price_per_image'      => null,
                'is_active'                    => true,
            ],
            [
                'name'                         => 'standard',
                'display_name'                 => 'Standard',
                'price_monthly'                => 79.00,
                'price_yearly'                 => 790.00,
                'max_brands'                   => 3,
                'max_users'                    => 5,
                'monthly_calendar_generations' => 15,
                'monthly_text_tokens'          => 300000,
                'monthly_images'               => 40,
                'has_export_excel'             => true,
                'has_activity_log'             => false,
                'has_advanced_roles'           => false,
                'has_api_access'               => false,
                'has_crm_integration'          => false,
                'has_auto_publishing'          => true,
                'has_analytics'                => false,
                'has_ab_testing'               => false,
                'has_own_api_keys'             => false,
                'allows_overage'               => false,
                'overage_price_per_1k_tokens'  => null,
                'overage_price_per_image'      => null,
                'is_active'                    => true,
            ],
            [
                'name'                         => 'pro',
                'display_name'                 => 'Pro',
                'price_monthly'                => 199.00,
                'price_yearly'                 => 1990.00,
                'max_brands'                   => 10,
                'max_users'                    => 20,
                'monthly_calendar_generations' => 50,
                'monthly_text_tokens'          => 700000,
                'monthly_images'               => 120,
                'has_export_excel'             => true,
                'has_activity_log'             => true,
                'has_advanced_roles'           => true,
                'has_api_access'               => true,
                'has_crm_integration'          => false,
                'has_auto_publishing'          => true,
                'has_analytics'                => true,
                'has_ab_testing'               => false,
                'has_own_api_keys'             => false,
                'allows_overage'               => true,
                'overage_price_per_1k_tokens'  => 0.0180,
                'overage_price_per_image'      => 0.0500,
                'is_active'                    => true,
            ],
            [
                'name'                         => 'unlimited',
                'display_name'                 => 'Unlimited',
                'price_monthly'                => 0.00,
                'price_yearly'                 => 0.00,
                'max_brands'                   => -1,
                'max_users'                    => -1,
                'monthly_calendar_generations' => -1,
                'monthly_text_tokens'          => -1,
                'monthly_images'               => -1,
                'has_export_excel'             => true,
                'has_activity_log'             => true,
                'has_advanced_roles'           => true,
                'has_api_access'               => true,
                'has_crm_integration'          => true,
                'has_auto_publishing'          => true,
                'has_analytics'                => true,
                'has_ab_testing'               => true,
                'has_own_api_keys'             => true,
                'allows_overage'               => true,
                'overage_price_per_1k_tokens'  => null,
                'overage_price_per_image'      => null,
                'is_active'                    => true,
            ],
        ];

        foreach ($plans as $planData) {
            $existing = Plan::where('name', $planData['name'])->first();
            if ($existing) {
                $existing->update($planData);
            } else {
                Plan::create($planData);
            }
        }

        $this->command->info('4 piani aggiornati con successo.');
    }
}
