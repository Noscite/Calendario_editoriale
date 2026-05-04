<?php

declare(strict_types=1);

namespace App\Domain\Review\Jobs;

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReplyTone;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Review\Services\ReviewReplyGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Genera una bozza di risposta a una review (job asincrono).
 *
 * Strategia di versionamento: ogni rigenerazione crea una NUOVA reply
 * in status='draft'. Le draft precedenti vengono marcate 'superseded'
 * così resta una storia delle versioni.
 */
class GenerateReplyDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries     = 2;
    public int $timeout   = 120;
    /** @var array<int,int> */
    public array $backoff = [60, 300];

    private string $tone;
    private ?string $strategyOverride;

    public function __construct(
        private readonly int $reviewId,
        ReplyTone $tone = ReplyTone::BrandDefault,
        ?string $strategyOverride = null,
    ) {
        $this->onQueue('default');
        $this->tone             = $tone->value;
        $this->strategyOverride = $strategyOverride;
    }

    public function handle(ReviewReplyGenerator $generator): void
    {
        $review = Review::withoutGlobalScope('organization')->find($this->reviewId);
        if (! $review) {
            Log::warning('[REPLY_DRAFT] Review non trovata', ['review_id' => $this->reviewId]);
            return;
        }

        // Marca le draft precedenti come superseded (storia versioni)
        ReviewReply::withoutGlobalScope('organization')
            ->where('review_id', $review->id)
            ->where('status', ReplyStatus::Draft->value)
            ->update(['status' => ReplyStatus::Superseded->value]);

        try {
            $tone   = ReplyTone::from($this->tone);
            $result = $generator->generate($review, $tone, $this->strategyOverride);

            ReviewReply::create([
                'organization_id'    => $review->organization_id,
                'review_id'          => $review->id,
                'status'             => ReplyStatus::Draft->value,
                'body'               => $result['body'],
                'tone_used'          => $result['tone_used'],
                'marketing_strategy' => $result['marketing_strategy'],
                'kb_chunks_used'     => $result['kb_chunks_used'],
                'generated_by_model' => $result['generated_by_model'],
                'input_tokens'       => $result['input_tokens'],
                'output_tokens'      => $result['output_tokens'],
                'generated_at'       => now(),
            ]);

            Log::info('[REPLY_DRAFT] Bozza generata', [
                'review_id'      => $review->id,
                'kb_chunks_used' => count($result['kb_chunks_used']),
                'output_tokens'  => $result['output_tokens'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[REPLY_DRAFT] Generazione fallita', [
                'review_id' => $review->id,
                'error'     => $e->getMessage(),
            ]);

            ReviewReply::create([
                'organization_id' => $review->organization_id,
                'review_id'       => $review->id,
                'status'          => ReplyStatus::Failed->value,
                'body'            => '[Generazione fallita]',
                'error_message'   => $e->getMessage(),
                'generated_at'    => now(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[REPLY_DRAFT] Job failed permanently', [
            'review_id' => $this->reviewId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
