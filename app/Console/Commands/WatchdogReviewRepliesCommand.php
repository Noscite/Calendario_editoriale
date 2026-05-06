<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Jobs\SendReplyJob;
use App\Domain\Review\Models\ReviewReply;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Watchdog per ReviewReply orfani.
 *
 * Recupera tre classi di reply che potrebbero non essere stati inviati a causa
 * di crash del worker Horizon, perdita di delayed jobs in Redis, o altri eventi
 * non graceful:
 *
 *  A. Reply 'approved' (modalità immediate) creati da >5 min senza essere mai
 *     inviati → ri-dispatcha SendReplyJob
 *  B. Reply 'draft' (modalità review) il cui delay è scaduto da >5 min →
 *     ri-dispatcha SendReplyJob (auto-promosso ad approved nel job stesso)
 *  C. Reply 'sending' da >60 min (oltre il backoff massimo 3*900s + buffer) →
 *     marca come 'failed' con error_message diagnostico
 *
 * Schedule: ogni 5 minuti via routes/console.php.
 */
class WatchdogReviewRepliesCommand extends Command
{
    protected $signature   = 'reviews:watchdog-replies {--dry-run : Mostra cosa farebbe senza eseguire azioni}';
    protected $description = 'Recupera ReviewReply orfani (auto-reply non inviati per race condition)';

    private const APPROVED_STALE_MIN     = 5;
    private const DRAFT_DELAY_BUFFER_MIN = 5;
    private const SENDING_STUCK_MIN      = 60;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $countA = $this->recoverApprovedOrphans($dryRun);
        $countB = $this->recoverDraftOrphans($dryRun);
        $countC = $this->markSendingStuck($dryRun);

        $total = $countA + $countB + $countC;

        if ($total > 0) {
            Log::info('[WATCHDOG_REPLIES] Run completed', [
                'approved_redispatched' => $countA,
                'draft_redispatched'    => $countB,
                'sending_marked_failed' => $countC,
                'dry_run'               => $dryRun,
            ]);
        }

        $this->info("Watchdog: A={$countA} B={$countB} C={$countC}" . ($dryRun ? ' (DRY RUN)' : ''));

        return self::SUCCESS;
    }

    /**
     * Caso A: reply 'approved' con was_auto_approved=true creati da >5 min,
     * mai inviati → re-dispatch SendReplyJob.
     */
    private function recoverApprovedOrphans(bool $dryRun): int
    {
        $threshold = Carbon::now()->subMinutes(self::APPROVED_STALE_MIN);

        $orphans = ReviewReply::query()
            ->withoutGlobalScope('organization')
            ->where('status', ReplyStatus::Approved->value)
            ->where('was_auto_approved', true)
            ->whereNull('sent_at')
            ->where('created_at', '<', $threshold)
            ->get();

        foreach ($orphans as $reply) {
            Log::warning('[WATCHDOG_REPLIES] Approved orphan detected', [
                'reply_id'    => $reply->id,
                'review_id'   => $reply->review_id,
                'created_at'  => $reply->created_at?->toIso8601String(),
                'age_minutes' => $reply->created_at ? Carbon::now()->diffInMinutes($reply->created_at) : null,
            ]);

            if (! $dryRun) {
                SendReplyJob::dispatch($reply->id);
            }
        }

        return $orphans->count();
    }

    /**
     * Caso B: reply 'draft' con was_auto_approved=true il cui delay è scaduto
     * da >5 min (basato su brand.auto_reply_delay_minutes).
     */
    private function recoverDraftOrphans(bool $dryRun): int
    {
        $candidates = ReviewReply::query()
            ->withoutGlobalScope('organization')
            ->with(['review.brand'])
            ->where('status', ReplyStatus::Draft->value)
            ->where('was_auto_approved', true)
            ->whereNull('sent_at')
            ->get();

        $count = 0;

        foreach ($candidates as $reply) {
            $brand = $reply->review?->brand;
            if (! $brand || ! $reply->created_at) {
                continue;
            }

            $delayMinutes = (int) ($brand->auto_reply_delay_minutes ?? 30);
            $expiresAt    = $reply->created_at->copy()->addMinutes($delayMinutes + self::DRAFT_DELAY_BUFFER_MIN);

            if ($expiresAt->isFuture()) {
                continue;
            }

            Log::warning('[WATCHDOG_REPLIES] Draft delayed orphan detected', [
                'reply_id'      => $reply->id,
                'review_id'     => $reply->review_id,
                'created_at'    => $reply->created_at->toIso8601String(),
                'delay_minutes' => $delayMinutes,
                'expired_min'   => Carbon::now()->diffInMinutes($expiresAt),
            ]);

            if (! $dryRun) {
                SendReplyJob::dispatch($reply->id);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Caso C: reply 'sending' da >60 min — il job non si è completato, segnale
     * di crash hard del worker. Marca come 'failed' per permettere retry manuale.
     */
    private function markSendingStuck(bool $dryRun): int
    {
        $threshold = Carbon::now()->subMinutes(self::SENDING_STUCK_MIN);

        $stuck = ReviewReply::query()
            ->withoutGlobalScope('organization')
            ->where('status', ReplyStatus::Sending->value)
            ->where('updated_at', '<', $threshold)
            ->get();

        foreach ($stuck as $reply) {
            Log::error('[WATCHDOG_REPLIES] Sending stuck detected', [
                'reply_id'    => $reply->id,
                'review_id'   => $reply->review_id,
                'updated_at'  => $reply->updated_at?->toIso8601String(),
                'stuck_min'   => $reply->updated_at ? Carbon::now()->diffInMinutes($reply->updated_at) : null,
            ]);

            if (! $dryRun) {
                $reply->update([
                    'status'        => ReplyStatus::Failed->value,
                    'error_message' => 'Watchdog: stuck in sending oltre 60 min, hard crash del worker presunto',
                ]);
            }
        }

        return $stuck->count();
    }
}
