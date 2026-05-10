<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Services;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Exceptions\CampaignLimitExceededException;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Organization\Models\Organization;

class CampaignLimitChecker
{
    /**
     * Verifica che l'organization possa avere un'altra campagna nello stato richiesto.
     *
     * @param  Organization $organization
     * @param  CampaignStatus $intendedStatus  lo stato che la campagna avrà (per draft/archived non c'è limite)
     * @param  ?Campaign $excludingCampaign campaign da escludere dal conteggio (es. su update di una esistente)
     *
     * @throws CampaignLimitExceededException se il limite è superato
     */
    public function ensureCanCreate(
        Organization $organization,
        CampaignStatus $intendedStatus,
        ?Campaign $excludingCampaign = null,
    ): void {
        if (! $intendedStatus->isActive()) {
            return;
        }

        $plan = $organization->plan;
        if (! $plan) {
            // Organization senza piano (legacy o trial puro): permissivo.
            return;
        }

        $maxActive = $plan->max_active_campaigns;
        if ($maxActive === null) {
            return;
        }

        $query = Campaign::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->whereIn('status', [
                CampaignStatus::Planning->value,
                CampaignStatus::Active->value,
            ]);

        if ($excludingCampaign) {
            $query->where('id', '!=', $excludingCampaign->id);
        }

        $currentActiveCount = $query->count();

        if ($currentActiveCount >= $maxActive) {
            throw new CampaignLimitExceededException(
                "Il tuo piano consente {$maxActive} campagn".
                ($maxActive === 1 ? 'a attiva' : 'e attive').
                ". Archivia una campagna conclusa o passa al piano superiore."
            );
        }
    }
}
