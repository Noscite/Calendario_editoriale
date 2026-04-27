<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Subscription\Events\SubscriptionActivated;
use App\Domain\Subscription\Events\SubscriptionCancelled;
use App\Domain\Subscription\Events\SubscriptionExpired;
use App\Domain\Subscription\Events\TrialExpired;
use App\Domain\Subscription\Events\TrialStarted;
use App\Domain\Subscription\Models\Subscription;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

final class SubscriptionStateService
{
    // ── Trial ──────────────────────────────────────────────────────

    /**
     * Avvia il periodo di prova per una subscription nuova.
     * Imposta status=trial, trial_started_at=now, trial_ends_at=now+14gg.
     *
     * @throws BusinessException se la subscription è già in trial o ha già un trial concluso
     */
    public function startTrial(Subscription $sub): void
    {
        if ($sub->trial_started_at !== null) {
            throw new BusinessException(
                'Il periodo di prova è già stato avviato o è già terminato.',
                'TRIAL_ALREADY_STARTED',
                409,
            );
        }

        DB::transaction(function () use ($sub): void {
            $sub->update([
                'status'           => Subscription::STATUS_TRIAL,
                'trial_started_at' => now(),
                'trial_ends_at'    => now()->addDays(config('trial.duration_days', 14)),
            ]);

            $this->syncOrganizationStatus($sub, OrganizationStatus::Trial);
        });

        TrialStarted::dispatch($sub->fresh());
    }

    /**
     * Sposta la subscription da trial → pending_payment.
     * Chiamato dal comando schedulato quando trial_ends_at < now().
     *
     * @throws BusinessException se lo stato corrente non è trial scaduto
     */
    public function expireTrial(Subscription $sub): void
    {
        if ($sub->status !== Subscription::STATUS_TRIAL) {
            throw new BusinessException(
                'La subscription non è in stato trial.',
                'INVALID_STATE_TRANSITION',
                422,
            );
        }

        if ($sub->trial_ends_at === null || $sub->trial_ends_at->isFuture()) {
            throw new BusinessException(
                'Il trial non è ancora scaduto.',
                'TRIAL_NOT_EXPIRED',
                422,
            );
        }

        DB::transaction(function () use ($sub): void {
            $sub->update(['status' => Subscription::STATUS_PENDING_PAYMENT]);
            $this->syncOrganizationStatus($sub, OrganizationStatus::PendingPayment);
        });

        TrialExpired::dispatch($sub->fresh());
    }

    // ── Attivazione pagata ─────────────────────────────────────────

    /**
     * Attiva un abbonamento pagato (bonifico confermato dall'admin).
     *
     * @throws BusinessException se lo stato corrente non permette l'attivazione
     * @throws BusinessException se il piano non è valido
     */
    public function activatePaidSubscription(
        Subscription $sub,
        int          $months,
        string       $plan,
        int          $adminUserId,
        string       $paymentReference,
        ?string      $notes = null,
    ): void {
        $allowedFromStates = [
            Subscription::STATUS_PENDING_PAYMENT,
            Subscription::STATUS_EXPIRED,
            Subscription::STATUS_CANCELLED,
        ];

        if (! in_array($sub->status, $allowedFromStates, strict: true)) {
            throw new BusinessException(
                "Non è possibile attivare una subscription in stato '{$sub->status}'. "
                . "Stati permessi: " . implode(', ', $allowedFromStates),
                'INVALID_STATE_TRANSITION',
                422,
            );
        }

        if (! in_array(strtolower($plan), Subscription::VALID_PLAN_NAMES, strict: true)) {
            throw new BusinessException(
                "Piano '{$plan}' non valido. Piani disponibili: " . implode(', ', Subscription::VALID_PLAN_NAMES),
                'INVALID_PLAN',
                422,
            );
        }

        if ($months < 1 || $months > 12) {
            throw new BusinessException(
                'Il numero di mesi deve essere compreso tra 1 e 12.',
                'INVALID_PAYMENT_MONTHS',
                422,
            );
        }

        DB::transaction(function () use ($sub, $months, $adminUserId, $paymentReference, $notes): void {
            $sub->update([
                'status'                => Subscription::STATUS_ACTIVE,
                'paid_period_starts_at' => now(),
                'paid_period_ends_at'   => now()->addMonths($months),
                'paid_period_months'    => $months,
                'activated_by_admin_id' => $adminUserId,
                'activated_at'          => now(),
                'payment_reference'     => $paymentReference,
                'payment_notes'         => $notes,
            ]);

            $this->syncOrganizationStatus($sub, OrganizationStatus::Active);
        });

        SubscriptionActivated::dispatch($sub->fresh());
    }

    // ── Estensione trial ───────────────────────────────────────────

    /**
     * Estende il trial di N giorni (default 7), anche se già scaduto.
     * Usato dall'admin Filament per offrire più tempo al prospect.
     */
    public function extendTrial(Subscription $sub, int $days = 7): void
    {
        if (! in_array($sub->status, [Subscription::STATUS_TRIAL, Subscription::STATUS_PENDING_PAYMENT], strict: true)) {
            throw new BusinessException(
                "Non è possibile estendere il trial di una subscription in stato '{$sub->status}'.",
                'INVALID_STATE_TRANSITION',
                422,
            );
        }

        $base = ($sub->trial_ends_at !== null && $sub->trial_ends_at->isFuture())
            ? $sub->trial_ends_at
            : now();

        DB::transaction(function () use ($sub, $base, $days): void {
            $sub->update([
                'status'        => Subscription::STATUS_TRIAL,
                'trial_ends_at' => $base->addDays($days),
            ]);

            $this->syncOrganizationStatus($sub, OrganizationStatus::Trial);
        });
    }

    // ── Scadenza periodo pagato ────────────────────────────────────

    /**
     * Sposta la subscription da active → expired quando paid_period_ends_at < now().
     * Chiamato dal comando schedulato.
     *
     * @throws BusinessException se il periodo pagato non è ancora scaduto
     */
    public function expirePaidSubscription(Subscription $sub): void
    {
        if ($sub->status !== Subscription::STATUS_ACTIVE) {
            throw new BusinessException(
                'La subscription non è in stato active.',
                'INVALID_STATE_TRANSITION',
                422,
            );
        }

        if ($sub->paid_period_ends_at === null || $sub->paid_period_ends_at->isFuture()) {
            throw new BusinessException(
                'Il periodo pagato non è ancora scaduto.',
                'PAID_PERIOD_NOT_EXPIRED',
                422,
            );
        }

        DB::transaction(function () use ($sub): void {
            $sub->update(['status' => Subscription::STATUS_EXPIRED]);
            $this->syncOrganizationStatus($sub, OrganizationStatus::Expired);
        });

        SubscriptionExpired::dispatch($sub->fresh());
    }

    // ── Cancellazione ─────────────────────────────────────────────

    /**
     * Cancella una subscription (qualsiasi stato → cancelled).
     */
    public function cancel(Subscription $sub, string $reason): void
    {
        DB::transaction(function () use ($sub, $reason): void {
            $sub->update([
                'status'       => Subscription::STATUS_CANCELLED,
                'payment_notes' => trim(($sub->payment_notes ?? '') . "\n[CANCELLATA] " . $reason),
            ]);

            $this->syncOrganizationStatus($sub, OrganizationStatus::Cancelled);
        });

        SubscriptionCancelled::dispatch($sub->fresh());
    }

    // ── Consumo risorse trial ──────────────────────────────────────

    /**
     * Incrementa il contatore token trial.
     *
     * @throws BusinessException se il budget trial verrebbe superato
     */
    public function incrementTrialTokenUsage(Subscription $sub, int $tokens): void
    {
        $budget = config('trial.token_budget', 30_000);

        if ($sub->trial_tokens_consumed + $tokens > $budget) {
            throw new BusinessException(
                "Budget token trial esaurito ({$sub->trial_tokens_consumed}/{$budget}). "
                . 'Attiva un piano per continuare a generare contenuti.',
                'TRIAL_TOKEN_LIMIT_EXCEEDED',
                429,
            );
        }

        $sub->increment('trial_tokens_consumed', $tokens);
    }

    /**
     * Incrementa il contatore calendari trial.
     *
     * @throws BusinessException se il budget trial verrebbe superato
     */
    public function incrementTrialCalendarUsage(Subscription $sub): void
    {
        $budget = config('trial.calendar_budget', 1);

        if ($sub->trial_calendars_generated >= $budget) {
            throw new BusinessException(
                "Hai già generato {$sub->trial_calendars_generated} calendario/i durante il trial (limite: {$budget}). "
                . 'Attiva un piano per generarne altri.',
                'TRIAL_CALENDAR_LIMIT_EXCEEDED',
                429,
            );
        }

        $sub->increment('trial_calendars_generated');
    }

    // ── Helpers privati ────────────────────────────────────────────

    /**
     * Mantiene organizations.subscription_status allineato con subscriptions.status.
     * Necessario per la compatibilità con il middleware check.limits e codice legacy.
     */
    private function syncOrganizationStatus(Subscription $sub, OrganizationStatus $status): void
    {
        $sub->organization()
            ->getQuery()
            ->update(['subscription_status' => $status->value]);
    }
}
