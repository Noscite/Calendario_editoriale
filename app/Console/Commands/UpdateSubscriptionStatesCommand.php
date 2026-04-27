<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Subscription\Events\SubscriptionExpiring;
use App\Domain\Subscription\Events\TrialExpiring;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Services\SubscriptionStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateSubscriptionStatesCommand extends Command
{
    protected $signature   = 'subscriptions:update-states';
    protected $description = 'Processa le transizioni di stato subscription: trial→pending_payment, active→expired, notifiche scadenza imminente.';

    public function handle(SubscriptionStateService $service): int
    {
        $counters = [
            'trial_expired'           => 0,
            'paid_expired'            => 0,
            'trial_expiring_notified' => 0,
            'paid_expiring_notified'  => 0,
            'errors'                  => 0,
        ];

        // ── 1. Trial scaduti → pending_payment ────────────────────
        $expiredTrials = Subscription::where('status', Subscription::STATUS_TRIAL)
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($expiredTrials as $sub) {
            try {
                $service->expireTrial($sub);
                $counters['trial_expired']++;
                $this->verboseLine("Trial scaduto → pending_payment: org #{$sub->organization_id}");
            } catch (Throwable $e) {
                $counters['errors']++;
                Log::error('[subscriptions:update-states] expireTrial fallito', [
                    'subscription_id'  => $sub->id,
                    'organization_id'  => $sub->organization_id,
                    'error'            => $e->getMessage(),
                ]);
                $this->verboseLine("  ERRORE org #{$sub->organization_id}: {$e->getMessage()}");
            }
        }

        // ── 2. Abbonamenti pagati scaduti → expired ───────────────
        $expiredPaid = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('paid_period_ends_at', '<', now())
            ->get();

        foreach ($expiredPaid as $sub) {
            try {
                $service->expirePaidSubscription($sub);
                $counters['paid_expired']++;
                $this->verboseLine("Pagato scaduto → expired: org #{$sub->organization_id}");
            } catch (Throwable $e) {
                $counters['errors']++;
                Log::error('[subscriptions:update-states] expirePaidSubscription fallito', [
                    'subscription_id'  => $sub->id,
                    'organization_id'  => $sub->organization_id,
                    'error'            => $e->getMessage(),
                ]);
                $this->verboseLine("  ERRORE org #{$sub->organization_id}: {$e->getMessage()}");
            }
        }

        // ── 3. Trial in scadenza tra 0-3 giorni → notifica ────────
        $trialExpiring = Subscription::trialExpiring(3)->get();

        foreach ($trialExpiring as $sub) {
            TrialExpiring::dispatch($sub);
            $counters['trial_expiring_notified']++;
            $this->verboseLine("Trial in scadenza (≤3gg): org #{$sub->organization_id}, scade {$sub->trial_ends_at?->toDateString()}");
        }

        // ── 4. Abbonamenti pagati in scadenza tra 0-7 giorni → notifica
        $paidExpiring = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereBetween('paid_period_ends_at', [now(), now()->addDays(7)])
            ->get();

        foreach ($paidExpiring as $sub) {
            SubscriptionExpiring::dispatch($sub);
            $counters['paid_expiring_notified']++;
            $this->verboseLine("Pagato in scadenza (≤7gg): org #{$sub->organization_id}, scade {$sub->paid_period_ends_at?->toDateString()}");
        }

        // ── Riepilogo ──────────────────────────────────────────────
        $this->info(sprintf(
            'subscriptions:update-states completato — trial scaduti: %d, pagati scaduti: %d, notifiche trial: %d, notifiche pagato: %d, errori: %d',
            $counters['trial_expired'],
            $counters['paid_expired'],
            $counters['trial_expiring_notified'],
            $counters['paid_expiring_notified'],
            $counters['errors'],
        ));

        try {
            Log::info('[subscriptions:update-states]', $counters);
        } catch (\Throwable) {
            // Log non bloccante: il comando ha completato con successo anche senza log su file
        }

        return $counters['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function verboseLine(string $message): void
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line($message);
        }
    }
}
