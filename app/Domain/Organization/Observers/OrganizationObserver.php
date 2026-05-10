<?php

declare(strict_types=1);

namespace App\Domain\Organization\Observers;

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Subscription;

final class OrganizationObserver
{
    /**
     * Quando un'organization viene creata, garantisce l'esistenza di una riga
     * `subscriptions` corrispondente. Mirror dei campi inline (subscription_status,
     * trial_ends_at, subscription_starts_at, subscription_ends_at) sulla tabella
     * subscriptions, per allineare la doppia source-of-truth tra Filament admin
     * (che scrive solo le colonne inline su `organizations`) e SubscriptionResource
     * (che legge da `subscriptions`).
     */
    public function created(Organization $organization): void
    {
        if ($organization->subscription_status === null) {
            return;
        }
        if ($organization->subscription()->exists()) {
            return;
        }

        Subscription::create([
            'organization_id'       => $organization->id,
            'status'                => self::mapStatus($organization->subscription_status),
            'trial_ends_at'         => $organization->trial_ends_at,
            'paid_period_starts_at' => $organization->subscription_starts_at,
            'paid_period_ends_at'   => $organization->subscription_ends_at,
        ]);
    }

    private static function mapStatus(OrganizationStatus $status): string
    {
        return match ($status) {
            OrganizationStatus::Trial          => Subscription::STATUS_TRIAL,
            OrganizationStatus::Active         => Subscription::STATUS_ACTIVE,
            OrganizationStatus::PendingPayment => Subscription::STATUS_PENDING_PAYMENT,
            OrganizationStatus::Expired        => Subscription::STATUS_EXPIRED,
            OrganizationStatus::Cancelled      => Subscription::STATUS_CANCELLED,
            // PastDue e Suspended non hanno equivalente diretto: trattati come pending_payment
            // (pagamento non effettivo, da risolvere) — l'admin li può poi cambiare a mano.
            OrganizationStatus::PastDue,
            OrganizationStatus::Suspended      => Subscription::STATUS_PENDING_PAYMENT,
        };
    }
}
