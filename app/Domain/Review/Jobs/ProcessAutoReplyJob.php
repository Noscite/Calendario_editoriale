<?php

declare(strict_types=1);

namespace App\Domain\Review\Jobs;

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReplyTone;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Review\Notifications\AutoReplyPreInvioNotification;
use App\Domain\Review\Services\AutoReplyEligibilityService;
use App\Domain\Review\Contracts\ReviewReplyGeneratorInterface;
use App\Domain\Review\Support\AutoReplyRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Orchestratore del flusso auto-reply.
 *
 * Trigger: ReviewObserver::updated quando status passa da 'new' a 'scored'.
 *
 * Flow:
 *   1. Eligibility check (brand settings + safety hard-coded)
 *   2. Genera bozza con il tono brand-configured
 *   3. Crea ReviewReply (status dipende da review_mode)
 *   4. Mode IMMEDIATE: dispatch SendReplyJob immediato; nessuna email pre-invio
 *      Mode REVIEW:    dispatch SendReplyJob con delay; invia email pre-invio
 *
 * L'utente può "bloccare" un reply ritardato marcandolo 'superseded' via UI.
 * Il SendReplyJob ha un safety check che skipperà reply non in stato approved/sending.
 */
class ProcessAutoReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries     = 2;
    public int $timeout   = 120;
    /** @var array<int,int> */
    public array $backoff = [60, 300];

    public function __construct(
        private readonly int $reviewId,
    ) {
        $this->onQueue('default');
    }

    public function handle(
        AutoReplyEligibilityService $eligibility,
        ReviewReplyGeneratorInterface $generator,
    ): void {
        $review = Review::withoutGlobalScope('organization')->find($this->reviewId);
        if (! $review) {
            Log::info('[AUTO_REPLY] Review non trovata', ['review_id' => $this->reviewId]);
            return;
        }

        $check = $eligibility->check($review);
        if (! $check['eligible']) {
            Log::info('[AUTO_REPLY] Skipped', [
                'review_id' => $review->id,
                'reason'    => $check['reason'],
            ]);
            return;
        }

        $brand        = $review->brand;
        $reviewMode   = (bool) $brand->auto_reply_review_mode;
        $delayMinutes = (int) ($brand->auto_reply_delay_minutes ?? 30);
        $toneValue    = (string) ($brand->auto_reply_tone ?? ReplyTone::BrandDefault->value);
        $tone         = ReplyTone::tryFrom($toneValue) ?? ReplyTone::BrandDefault;

        $reply = null;
        try {
            $result = $generator->generate($review, $tone, marketingStrategyOverride: null);

            $reply = ReviewReply::create([
                'organization_id'    => $review->organization_id,
                'review_id'          => $review->id,
                'status'             => $reviewMode ? ReplyStatus::Draft->value : ReplyStatus::Approved->value,
                'body'               => $result['body'],
                'tone_used'          => $result['tone_used'],
                'marketing_strategy' => $result['marketing_strategy'],
                'kb_chunks_used'     => $result['kb_chunks_used'],
                'generated_by_model' => $result['generated_by_model'],
                'input_tokens'       => $result['input_tokens'],
                'output_tokens'      => $result['output_tokens'],
                'generated_at'       => now(),
                'was_auto_approved'  => true,
                'notify_after_send'  => ! $reviewMode,
                'approved_by_user_id'=> null,
                'approved_at'        => $reviewMode ? null : now(),
            ]);

            if ($reviewMode) {
                SendReplyJob::dispatch($reply->id)->delay(now()->addMinutes($delayMinutes));

                $recipient = AutoReplyRecipient::for($review->organization);
                if ($recipient !== null) {
                    $recipient->notify(new AutoReplyPreInvioNotification($reply));
                }

                Log::info('[AUTO_REPLY] Created delayed reply + pre-invio email', [
                    'review_id' => $review->id,
                    'reply_id'  => $reply->id,
                    'mode'      => 'delayed',
                    'delay_min' => $delayMinutes,
                ]);
            } else {
                SendReplyJob::dispatch($reply->id);

                Log::info('[AUTO_REPLY] Created reply, immediate send dispatched', [
                    'review_id' => $review->id,
                    'reply_id'  => $reply->id,
                    'mode'      => 'immediate',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[AUTO_REPLY] Failed', [
                'review_id' => $review->id,
                'reply_id'  => $reply?->id,
                'error'     => $e->getMessage(),
            ]);
            $reply?->update([
                'status'        => ReplyStatus::Failed->value,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[AUTO_REPLY] Job failed permanently', [
            'review_id' => $this->reviewId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
