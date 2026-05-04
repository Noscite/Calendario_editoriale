<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\UsageLog;
use Carbon\Carbon;

/**
 * Subscription gating per le risposte alle recensioni.
 *
 * Quota mensile letta da `subscription_plans.monthly_reply_count`:
 *   null → illimitato
 *   0    → feature disabilitata
 *   N    → cap mensile, decrementato al SEND riuscito
 *
 * Counter persistito in `usage_tracking.replies_sent` per il periodo
 * corrente (mese in corso, già gestito da UsageLogRepository).
 */
final class ReplyQuotaService
{
    public function canSendReply(Organization $organization): bool
    {
        $limit = $this->limit($organization);

        if ($limit === null) {
            return true; // unlimited
        }
        if ($limit === 0) {
            return false; // feature disabled
        }

        return $this->repliesUsedThisMonth($organization) < $limit;
    }

    /**
     * @return int|null  null = illimitato
     */
    public function remainingMonthlyReplies(Organization $organization): ?int
    {
        $limit = $this->limit($organization);
        if ($limit === null) {
            return null;
        }
        if ($limit === 0) {
            return 0;
        }
        return max(0, $limit - $this->repliesUsedThisMonth($organization));
    }

    public function repliesUsedThisMonth(Organization $organization): int
    {
        $usage = $this->currentPeriodUsage($organization->id);
        return (int) ($usage->replies_sent ?? 0);
    }

    public function recordReplySent(int $organizationId): void
    {
        $usage = $this->currentPeriodUsage($organizationId);
        $usage->increment('replies_sent');
    }

    /**
     * @return int|null  null = illimitato; 0 = disabilitato; N = cap mensile
     */
    public function limit(Organization $organization): ?int
    {
        $plan = $this->resolvePlan($organization);
        if ($plan === null) {
            return 0;
        }

        $value = $plan->monthly_reply_count;
        if ($value === null) {
            return null;
        }
        return (int) $value;
    }

    public function isFeatureEnabled(Organization $organization): bool
    {
        return $this->limit($organization) !== 0;
    }

    /**
     * @return array{limit:?int, used:int, remaining:?int, unlimited:bool, feature_enabled:bool, resets_at:string}
     */
    public function summary(Organization $organization): array
    {
        $limit     = $this->limit($organization);
        $used      = $this->repliesUsedThisMonth($organization);
        $unlimited = $limit === null;
        $remaining = $unlimited ? null : max(0, ($limit ?? 0) - $used);

        return [
            'limit'           => $limit,
            'used'            => $used,
            'remaining'       => $remaining,
            'unlimited'       => $unlimited,
            'feature_enabled' => $limit !== 0,
            'resets_at'       => Carbon::now()->endOfMonth()->toIso8601String(),
        ];
    }

    private function currentPeriodUsage(int $organizationId): UsageLog
    {
        $now = Carbon::now();

        $usage = UsageLog::query()
            ->where('organization_id', $organizationId)
            ->where('period_start', '<=', $now)
            ->where('period_end', '>=', $now)
            ->first();

        if ($usage !== null) {
            return $usage;
        }

        return UsageLog::create([
            'organization_id'           => $organizationId,
            'period_start'              => $now->copy()->startOfMonth(),
            'period_end'                => $now->copy()->endOfMonth(),
            'calendar_generations_used' => 0,
            'text_tokens_used'          => 0,
            'images_generated'          => 0,
            'replies_sent'              => 0,
        ]);
    }

    private function resolvePlan(Organization $organization): ?Plan
    {
        if ($organization->plan_id === null) {
            return null;
        }
        return Plan::find($organization->plan_id);
    }
}
