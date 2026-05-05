<?php

declare(strict_types=1);

namespace App\Domain\Review\Services;

use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Enums\Sentiment;
use App\Domain\Review\Enums\Urgency;
use App\Domain\Review\Models\Review;
use App\Domain\Subscription\Services\ReplyQuotaService;

/**
 * Decide se una review è eligibile per auto-reply.
 *
 * Hard-coded safety checks (sempre attivi):
 *   - is_fake_suspect → STOP
 *   - urgency = high → STOP
 *   - status != 'scored' → STOP
 *
 * Brand-configurable:
 *   - auto_reply_enabled
 *   - auto_reply_min_rating
 *   - auto_reply_only_positive_sentiment
 *
 * Quota: usa ReplyQuotaService di M3.
 */
final class AutoReplyEligibilityService
{
    public function __construct(
        private readonly ReplyQuotaService $quotaService,
    ) {
    }

    /**
     * @return array{eligible: bool, reason: string|null}
     */
    public function check(Review $review): array
    {
        $brand = $review->brand;
        if ($brand === null) {
            return ['eligible' => false, 'reason' => 'brand_missing'];
        }

        if (! (bool) $brand->auto_reply_enabled) {
            return ['eligible' => false, 'reason' => 'auto_reply_disabled'];
        }

        // ── Hard-coded safety (non bypassabili dalle settings brand) ────
        if ((bool) $review->is_fake_suspect) {
            return ['eligible' => false, 'reason' => 'fake_suspect'];
        }

        $urgencyValue = $review->urgency instanceof Urgency
            ? $review->urgency->value
            : (string) $review->getRawOriginal('urgency');
        if ($urgencyValue === Urgency::High->value) {
            return ['eligible' => false, 'reason' => 'urgency_high'];
        }

        $statusValue = $review->status instanceof ReviewStatus
            ? $review->status->value
            : (string) $review->getRawOriginal('status');
        if ($statusValue !== ReviewStatus::Scored->value) {
            return ['eligible' => false, 'reason' => 'not_scored'];
        }

        // ── Configurazione brand ──────────────────────────────────
        if ((int) $review->rating < (int) $brand->auto_reply_min_rating) {
            return ['eligible' => false, 'reason' => 'rating_below_threshold'];
        }

        if ((bool) $brand->auto_reply_only_positive_sentiment) {
            $sentimentValue = $review->sentiment instanceof Sentiment
                ? $review->sentiment->value
                : (string) $review->getRawOriginal('sentiment');
            if ($sentimentValue !== Sentiment::Positive->value) {
                return ['eligible' => false, 'reason' => 'sentiment_not_positive'];
            }
        }

        // ── Quota ─────────────────────────────────────────────────
        if ($review->organization === null) {
            return ['eligible' => false, 'reason' => 'organization_missing'];
        }
        if (! $this->quotaService->canSendReply($review->organization)) {
            return ['eligible' => false, 'reason' => 'quota_exceeded'];
        }

        return ['eligible' => true, 'reason' => null];
    }
}
