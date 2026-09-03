<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Project\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Wallet a crediti-post: pool prepagato separato dalla quota mensile del
 * piano, venduto a €/post (Plan.post_credit_price_eur). Il saldo è sempre
 * derivato dal ledger (SUM(delta)), mai una colonna mutabile — ogni
 * movimento (ricarica manuale, consumo per generazione) resta tracciato.
 */
class PostCreditService
{
    public function balance(int $organizationId): int
    {
        return (int) DB::table('post_credit_ledger')
            ->where('organization_id', $organizationId)
            ->sum('delta');
    }

    /**
     * Stima quanti post genererà un progetto, stessa logica di
     * GenerateCalendarJob::handle() (posts_per_week per piattaforma, default
     * 2 se non configurato) moltiplicata per le settimane nel range date.
     */
    public function estimatePostsForProject(Project $project): int
    {
        $platforms = $project->platforms ?? [];
        if (empty($platforms) || ! $project->start_date || ! $project->end_date) {
            return 0;
        }

        $postsPerWeekTotal = 0;
        foreach ($platforms as $platform) {
            $postsPerWeekTotal += (int) ($project->posts_per_week[$platform] ?? 2);
        }

        $days  = max(1, $project->start_date->diffInDays($project->end_date) + 1);
        $weeks = max(1, (int) ceil($days / 7));

        return $postsPerWeekTotal * $weeks;
    }

    /**
     * True se l'organizzazione ha ricevuto almeno una ricarica vera
     * (reason purchase/adjustment). Finché non è mai stata accreditata,
     * il wallet non la riguarda: nessun blocco, comportamento invariato
     * rispetto a prima dell'introduzione di questa feature. Questo evita
     * che ogni organizzazione esistente (saldo 0 per definizione, la
     * tabella è nuova) si veda bloccare la generazione dall'oggi al domani.
     *
     * Deliberatamente esclude reason=consumption: ogni post AI-generated
     * scrive un debito automatico (PostObserver / GenerateCalendarJob) a
     * prescindere dal fatto che l'organizzazione usi o meno il wallet — se
     * quei debiti "auto-iscrivessero", la prima generazione di QUALSIASI
     * organizzazione la farebbe scattare a saldo negativo e bloccarla alla
     * generazione successiva, esattamente il problema che questo metodo
     * deve evitare.
     */
    public function isWalletEnrolled(int $organizationId): bool
    {
        return DB::table('post_credit_ledger')
            ->where('organization_id', $organizationId)
            ->whereIn('reason', ['purchase', 'adjustment'])
            ->exists();
    }

    public function hasSufficientCredit(int $organizationId, int $postsNeeded): bool
    {
        if ($postsNeeded <= 0 || ! $this->isWalletEnrolled($organizationId)) {
            return true;
        }

        return $this->balance($organizationId) >= $postsNeeded;
    }

    /**
     * Ricarica manuale (o storno). Stesso pattern di tracciabilità già usato
     * per l'attivazione manuale degli abbonamenti (Subscription::activated_by_admin_id
     * / payment_reference / payment_notes).
     */
    public function credit(
        int $organizationId,
        int $amount,
        string $reason = 'purchase',
        ?int $adminId = null,
        ?string $paymentReference = null,
        ?string $note = null,
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Il credito da accreditare deve essere positivo.');
        }

        DB::table('post_credit_ledger')->insert([
            'organization_id'     => $organizationId,
            'delta'               => $amount,
            'reason'              => $reason,
            'post_id'             => null,
            'created_by_admin_id' => $adminId,
            'payment_reference'   => $paymentReference,
            'note'                => $note,
            'created_at'          => now(),
        ]);
    }

    /**
     * Consumo per generazione. $count > 1 copre il percorso bulk-insert
     * (GenerateCalendarJob::Post::insert()), che bypassa gli eventi Eloquent
     * e quindi il PostObserver — un'unica riga di ledger per il chunk invece
     * di N, con post_id nullo perché non riferita a un singolo post.
     */
    public function debit(int $organizationId, int $count = 1, ?int $postId = null): void
    {
        if ($count <= 0) {
            return;
        }

        DB::table('post_credit_ledger')->insert([
            'organization_id'     => $organizationId,
            'delta'               => -$count,
            'reason'              => 'consumption',
            'post_id'             => $postId,
            'created_by_admin_id' => null,
            'payment_reference'   => null,
            'note'                => null,
            'created_at'          => now(),
        ]);
    }
}
