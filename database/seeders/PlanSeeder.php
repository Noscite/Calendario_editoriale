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
                'monthly_calendar_generations' => 2,
                'monthly_text_tokens'          => 50000,
                'monthly_images'               => 10,
                'is_active'                    => true,
                'allows_overage'               => false,
            ],
            [
                'name'                         => 'standard',
                'display_name'                 => 'Standard',
                'price_monthly'                => 79.00,
                'price_yearly'                 => 790.00,
                'max_brands'                   => 3,
                'max_users'                    => 5,
                'monthly_calendar_generations' => 10,
                'monthly_text_tokens'          => 200000,
                'monthly_images'               => 50,
                'is_active'                    => true,
                'allows_overage'               => false,
            ],
            [
                'name'                         => 'pro',
                'display_name'                 => 'Pro',
                'price_monthly'                => 199.00,
                'price_yearly'                 => 1990.00,
                'max_brands'                   => 10,
                'max_users'                    => 20,
                'monthly_calendar_generations' => 30,
                'monthly_text_tokens'          => 500000,
                'monthly_images'               => 200,
                'is_active'                    => true,
                'allows_overage'               => true,
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
                'is_active'                    => true,
                'allows_overage'               => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan);
        }

        $this->command->info('4 piani creati con successo.');
    }
}
