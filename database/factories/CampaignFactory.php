<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        // NB: Organization creata senza subscription_status per evitare collisione
        // con OrganizationObserver che auto-creerebbe una Subscription.
        $org = Organization::create([
            'name'      => 'Org C ' . Str::random(8),
            'slug'      => 'org-c-' . Str::random(12),
            'email'     => $this->faker->unique()->safeEmail(),
            'is_active' => true,
        ]);

        return [
            'organization_id'    => $org->id,
            'name'               => $this->faker->sentence(3),
            'brief'              => $this->faker->paragraphs(2, true),
            'status'             => CampaignStatus::Draft,
            'start_date'         => now()->addDays(7),
            'end_date'           => now()->addDays(37),
            'created_by_user_id' => null,
        ];
    }
}
