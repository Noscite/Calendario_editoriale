<?php

declare(strict_types=1);

namespace App\Domain\Review\Jobs;

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Review\Services\GoogleReviewReplier;
use App\Domain\Social\Exceptions\TokenExpiredException;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Services\TokenRefreshService;
use App\Domain\Subscription\Services\ReplyQuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries     = 3;
    public int $timeout   = 60;
    /** @var array<int,int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly int $reviewReplyId,
    ) {
        $this->onQueue('pubblicazione');
    }

    public function handle(
        GoogleReviewReplier $replier,
        TokenRefreshService $tokenService,
        ReplyQuotaService $quotaService,
    ): void {
        $reply = ReviewReply::withoutGlobalScope('organization')->find($this->reviewReplyId);
        if (! $reply) {
            Log::warning('[REPLY_SEND] Reply non trovata', ['reply_id' => $this->reviewReplyId]);
            return;
        }

        // Idempotenza: solo approved/sending sono inviabili
        $status = $reply->status instanceof ReplyStatus ? $reply->status : ReplyStatus::tryFrom((string) $reply->getRawOriginal('status'));
        if (! in_array($status, [ReplyStatus::Approved, ReplyStatus::Sending], true)) {
            Log::info('[REPLY_SEND] Reply non in stato inviabile, skip', [
                'reply_id' => $reply->id,
                'status'   => $status?->value,
            ]);
            return;
        }

        $review = $reply->review()->withoutGlobalScope('organization')->first();
        if (! $review) {
            $reply->update(['status' => ReplyStatus::Failed->value, 'error_message' => 'Review collegata non trovata']);
            return;
        }

        $connection = SocialConnection::query()
            ->where('brand_id', $review->brand_id)
            ->where('platform', 'google_business')
            ->where('is_active', true)
            ->first();

        if (! $connection) {
            $reply->update([
                'status'        => ReplyStatus::Failed->value,
                'error_message' => 'Nessuna SocialConnection google_business attiva per il brand',
            ]);
            throw new RuntimeException('Missing google_business SocialConnection for brand ' . $review->brand_id);
        }

        $reply->update(['status' => ReplyStatus::Sending->value]);

        try {
            $result = $replier->reply($review, $connection, $reply->body);
        } catch (TokenExpiredException $e) {
            Log::warning('[REPLY_SEND] Token expired, refresh + retry', ['reply_id' => $reply->id]);
            $connection = $tokenService->refreshIfNeeded($connection);
            $result     = $replier->reply($review, $connection, $reply->body);
        }

        if (($result['success'] ?? false) === true) {
            $reply->update([
                'status'            => ReplyStatus::Sent->value,
                'sent_at'           => now(),
                'external_reply_id' => $result['external_reply_id'] ?? null,
                'error_message'     => null,
            ]);
            $review->update(['status' => ReviewStatus::Replied->value]);
            $quotaService->recordReplySent($review->organization_id);

            Log::info('[REPLY_SEND] Reply inviata', [
                'reply_id'  => $reply->id,
                'review_id' => $review->id,
            ]);
            return;
        }

        $error = (string) ($result['error'] ?? 'Errore sconosciuto');
        $reply->update([
            'status'        => ReplyStatus::Failed->value,
            'error_message' => $error,
            'retry_count'   => ($reply->retry_count ?? 0) + 1,
        ]);

        throw new RuntimeException("Send reply failed: {$error}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[REPLY_SEND] Job failed permanently', [
            'reply_id' => $this->reviewReplyId,
            'error'    => $exception->getMessage(),
        ]);

        $reply = ReviewReply::withoutGlobalScope('organization')->find($this->reviewReplyId);
        if ($reply !== null) {
            $reply->update([
                'status'        => ReplyStatus::Failed->value,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
