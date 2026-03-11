<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Notification\Models\Notification;
use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller per la gestione dei webhook Stripe.
 *
 * Verifica la firma con Stripe\Webhook::constructEvent() e gestisce:
 *   - customer.subscription.updated    → aggiorna plan e status
 *   - customer.subscription.deleted    → downgrade a piano basic
 *   - invoice.payment_failed           → status past_due + notifica
 *   - invoice.payment_succeeded        → status active
 *
 * Per ogni evento: crea record in activity_logs.
 * Risponde sempre 200 OK (anche per eventi non gestiti).
 *
 * Route: POST /api/webhooks/stripe (senza auth:sanctum)
 */
final class StripeWebhookController extends Controller
{
    /**
     * Punto di ingresso per tutti i webhook Stripe.
     */
    public function handle(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $secret    = config('services.stripe.webhook_secret', '');

        // Verifica firma crittografica Stripe
        try {
            if (! $secret) {
                Log::warning('[STRIPE] STRIPE_WEBHOOK_SECRET non configurato');
                return response('Webhook secret not configured', 500);
            }

            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('[STRIPE] Payload invalido', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('[STRIPE] Firma non valida', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        $eventType = $event->type ?? '';
        $eventData = (array) ($event->data->object ?? []);

        Log::info("[STRIPE] Evento ricevuto: {$eventType}");

        match ($eventType) {
            'customer.subscription.updated'   => $this->handleSubscriptionUpdated($eventData),
            'customer.subscription.deleted'   => $this->handleSubscriptionDeleted($eventData),
            'invoice.payment_failed'          => $this->handlePaymentFailed($eventData),
            'invoice.payment_succeeded'       => $this->handlePaymentSucceeded($eventData),
            default                           => Log::info("[STRIPE] Evento non gestito: {$eventType}"),
        };

        // Log su activity_logs
        $this->logActivity($eventType, $eventData);

        return response('', 200);
    }

    // ──────────────────────────────────────────────────────────
    //  Event handlers
    // ──────────────────────────────────────────────────────────

    /**
     * customer.subscription.updated → aggiorna plan e subscription_status.
     */
    private function handleSubscriptionUpdated(array $data): void
    {
        $stripeCustomerId   = $data['customer'] ?? null;
        $stripeSubId        = $data['id'] ?? null;
        $stripePriceId      = $data['items']['data'][0]['price']['id'] ?? null;
        $stripeStatus       = $data['status'] ?? 'active';

        if (!$stripeCustomerId) {
            return;
        }

        $organization = Organization::where('stripe_customer_id', $stripeCustomerId)->first();
        if (!$organization) {
            Log::warning("[STRIPE] Organizzazione non trovata per customer_id: {$stripeCustomerId}");
            return;
        }

        // Mappa lo status Stripe allo status interno
        $internalStatus = $this->mapStripeStatus($stripeStatus);

        $updates = [
            'subscription_status'    => $internalStatus,
            'stripe_subscription_id' => $stripeSubId,
        ];

        // Se c'è un price_id cerchiamo il piano corrispondente
        if ($stripePriceId) {
            $plan = Plan::where('stripe_price_id', $stripePriceId)->first();
            if ($plan) {
                $updates['plan_id'] = $plan->id;
                Log::info("[STRIPE] Piano aggiornato a {$plan->name} per org #{$organization->id}");
            }
        }

        $organization->update($updates);

        Log::info("[STRIPE] subscription.updated — org #{$organization->id}", [
            'status' => $internalStatus->value,
        ]);
    }

    /**
     * customer.subscription.deleted → downgrade a piano basic.
     */
    private function handleSubscriptionDeleted(array $data): void
    {
        $stripeCustomerId = $data['customer'] ?? null;

        if (!$stripeCustomerId) {
            return;
        }

        $organization = Organization::where('stripe_customer_id', $stripeCustomerId)->first();
        if (!$organization) {
            return;
        }

        // Trova il piano basic (fallback)
        $basicPlan = Plan::where('slug', 'basic')->orWhere('is_default', true)->first();

        $organization->update([
            'subscription_status'    => OrganizationStatus::Cancelled,
            'plan_id'                => $basicPlan?->id ?? $organization->plan_id,
            'stripe_subscription_id' => null,
            'subscription_ends_at'   => now(),
        ]);

        // Notifica tutti gli utenti admin dell'organizzazione
        $this->notifyOrganizationUsers(
            $organization,
            'Abbonamento cancellato',
            'Il tuo abbonamento è stato cancellato. Sei stato spostato al piano gratuito.',
            'subscription_cancelled',
        );

        Log::info("[STRIPE] subscription.deleted — org #{$organization->id} → piano basic");
    }

    /**
     * invoice.payment_failed → status past_due + notifica.
     */
    private function handlePaymentFailed(array $data): void
    {
        $stripeCustomerId = $data['customer'] ?? null;
        $invoiceId        = $data['id'] ?? null;
        $amountDue        = $data['amount_due'] ?? 0;

        if (!$stripeCustomerId) {
            return;
        }

        $organization = Organization::where('stripe_customer_id', $stripeCustomerId)->first();
        if (!$organization) {
            return;
        }

        $organization->update(['subscription_status' => OrganizationStatus::PastDue]);

        $amountFormatted = number_format($amountDue / 100, 2, ',', '.') . ' €';

        $this->notifyOrganizationUsers(
            $organization,
            'Pagamento fallito',
            "Il pagamento di {$amountFormatted} non è andato a buon fine. "
            . 'Aggiorna il metodo di pagamento per mantenere il tuo account attivo.',
            'payment_failed',
        );

        Log::info("[STRIPE] invoice.payment_failed — org #{$organization->id}", [
            'invoice_id' => $invoiceId,
            'amount_due' => $amountDue,
        ]);
    }

    /**
     * invoice.payment_succeeded → reset status ad active.
     */
    private function handlePaymentSucceeded(array $data): void
    {
        $stripeCustomerId = $data['customer'] ?? null;

        if (!$stripeCustomerId) {
            return;
        }

        $organization = Organization::where('stripe_customer_id', $stripeCustomerId)->first();
        if (!$organization) {
            return;
        }

        // Resetta lo stato solo se era past_due
        if ($organization->subscription_status === OrganizationStatus::PastDue) {
            $organization->update(['subscription_status' => OrganizationStatus::Active]);
            Log::info("[STRIPE] invoice.payment_succeeded — org #{$organization->id} → active");
        }
    }

    // ──────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Mappa lo status Stripe all'enum OrganizationStatus interno.
     */
    private function mapStripeStatus(string $stripeStatus): OrganizationStatus
    {
        return match ($stripeStatus) {
            'active', 'trialing' => OrganizationStatus::Active,
            'past_due'           => OrganizationStatus::PastDue,
            'canceled'           => OrganizationStatus::Cancelled,
            'unpaid'             => OrganizationStatus::Suspended,
            default              => OrganizationStatus::Active,
        };
    }

    /**
     * Notifica gli utenti dell'organizzazione con un messaggio.
     */
    private function notifyOrganizationUsers(
        Organization $organization,
        string $title,
        string $message,
        string $type,
    ): void {
        try {
            $users = $organization->users()->limit(10)->get();

            foreach ($users as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type'    => $type,
                    'title'   => $title,
                    'message' => $message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("[STRIPE] Impossibile creare notifiche", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Crea un record in activity_logs per il webhook ricevuto.
     */
    private function logActivity(string $eventType, array $eventData): void
    {
        try {
            $stripeCustomerId = $eventData['customer'] ?? null;
            $organization     = $stripeCustomerId
                ? Organization::where('stripe_customer_id', $stripeCustomerId)->first()
                : null;

            if (!$organization) {
                return;
            }

            // Prende il primo utente admin come actor
            $user = $organization->users()->orderBy('id')->first();
            if (!$user) {
                return;
            }

            DB::table('activity_logs')->insert([
                'user_id'         => $user->id,
                'organization_id' => $organization->id,
                'action'          => 'stripe_webhook',
                'entity_type'     => 'stripe_event',
                'entity_name'     => $eventType,
                'details'         => json_encode([
                    'event_type' => $eventType,
                    'object_id'  => $eventData['id'] ?? null,
                ]),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("[STRIPE] Impossibile loggare activity", ['error' => $e->getMessage()]);
        }
    }
}
