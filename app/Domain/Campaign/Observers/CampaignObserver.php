<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Observers;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Exceptions\CampaignInvalidTransitionException;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignLimitChecker;
use App\Domain\Organization\Models\Organization;

class CampaignObserver
{
    public function __construct(private readonly CampaignLimitChecker $limitChecker) {}

    public function creating(Campaign $campaign): void
    {
        // Normalizza lo status in enum se è stringa (factory/array fillable)
        $status = $campaign->status instanceof CampaignStatus
            ? $campaign->status
            : CampaignStatus::tryFrom((string) $campaign->status);

        if ($status?->isActive() !== true) {
            return;
        }

        $org = Organization::find($campaign->organization_id);
        if ($org) {
            $this->limitChecker->ensureCanCreate($org, $status);
        }
    }

    public function updating(Campaign $campaign): void
    {
        if (! $campaign->isDirty('status')) {
            return;
        }

        $original = $campaign->getOriginal('status');
        $oldStatus = $original instanceof CampaignStatus
            ? $original
            : CampaignStatus::from((string) $original);
        $newStatus = $campaign->status instanceof CampaignStatus
            ? $campaign->status
            : CampaignStatus::from((string) $campaign->status);

        if (! $oldStatus->canTransitionTo($newStatus)) {
            throw new CampaignInvalidTransitionException(
                "Transizione non consentita: {$oldStatus->label()} → {$newStatus->label()}."
            );
        }

        // Nuovo stato attivo da uno passivo → ri-valida plan limit
        if ($newStatus->isActive() && ! $oldStatus->isActive()) {
            $org = Organization::find($campaign->organization_id);
            if ($org) {
                $this->limitChecker->ensureCanCreate($org, $newStatus, $campaign);
            }
        }
    }
}
