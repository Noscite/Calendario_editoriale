<?php

declare(strict_types=1);

namespace App\Domain\Organization\Observers;

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Subscription;

final class OrganizationObserver
{
    private const DEFAULT_TRIAL_DAYS = 14;
    private const DEFAULT_PAID_MONTHS = 12;

    /**
     * Quando un'organization viene creata, garantisce l'esistenza di una riga
     * `subscriptions` corrispondente. Mirror dei campi inline (subscription_status,
     * trial_ends_at, subscription_starts_at, subscription_ends_at) sulla tabella
     * subscriptions, per allineare la doppia source-of-truth tra Filament admin
     * (che scrive solo le colonne inline su `organizations`) e SubscriptionResource
     * (che legge da `subscriptions`).
     *
     * Se status=active/trial e mancano le date corrispondenti sui campi inline,
     * applica default ragionevoli: trial → +14gg, active → +12 mesi. Senza
     * questi default Subscription::isActive()/isInTrial() ritornerebbero false
     * e l'utente sarebbe rimbalzato su /subscription/inactive nonostante lo
     * status "active" configurato dall'admin.
     */
    public function created(Organization $organization): void
    {
        if ($organization->subscription_status === null) {
            return;
        }
        if ($organization->subscription()->exists()) {
            return;
        }

        $status = self::mapStatus($organization->subscription_status);
        $attrs = [
            'organization_id' => $organization->id,
            'status'          => $status,
        ];

        if ($status === Subscription::STATUS_TRIAL) {
            $attrs['trial_started_at'] = $organization->subscription_starts_at ?? now();
            $attrs['trial_ends_at']    = $organization->trial_ends_at
                ?? now()->addDays(self::DEFAULT_TRIAL_DAYS);
        } elseif ($status === Subscription::STATUS_ACTIVE) {
            $start = $organization->subscription_starts_at ?? now();
            $end   = $organization->subscription_ends_at
                ?? $start->copy()->addMonths(self::DEFAULT_PAID_MONTHS);
            $attrs['paid_period_starts_at'] = $start;
            $attrs['paid_period_ends_at']   = $end;
            $attrs['paid_period_months']    = self::DEFAULT_PAID_MONTHS;
            $attrs['activated_at']          = now();
        } else {
            // Stati passivi (expired/cancelled/pending_payment): mirror diretto.
            $attrs['trial_ends_at']         = $organization->trial_ends_at;
            $attrs['paid_period_starts_at'] = $organization->subscription_starts_at;
            $attrs['paid_period_ends_at']   = $organization->subscription_ends_at;
        }

        Subscription::create($attrs);
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
