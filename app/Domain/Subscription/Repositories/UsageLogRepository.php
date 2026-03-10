<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Repositories;

use App\Domain\Subscription\Contracts\UsageLogRepositoryInterface;
use App\Domain\Subscription\Models\UsageLog;
use Carbon\Carbon;

final class UsageLogRepository implements UsageLogRepositoryInterface
{
    public function __construct(
        private readonly UsageLog $model,
    ) {}

    public function findCurrentPeriod(int $organizationId): ?UsageLog
    {
        $now = Carbon::now();

        return $this->model
            ->where('organization_id', $organizationId)
            ->where('period_start', '<=', $now)
            ->where('period_end', '>=', $now)
            ->first();
    }

    public function findOrCreateCurrentPeriod(int $organizationId): UsageLog
    {
        $existing = $this->findCurrentPeriod($organizationId);

        if ($existing !== null) {
            return $existing;
        }

        return $this->initializeForOrganization($organizationId);
    }

    public function getStatsByOrganization(int $organizationId, ?string $period = null): ?UsageLog
    {
        if ($period === null) {
            return $this->findCurrentPeriod($organizationId);
        }

        $date = Carbon::parse($period);

        return $this->model
            ->where('organization_id', $organizationId)
            ->where('period_start', '<=', $date)
            ->where('period_end', '>=', $date)
            ->first();
    }

    public function incrementCalendarGenerations(int $organizationId, int $amount = 1): UsageLog
    {
        $usage = $this->findOrCreateCurrentPeriod($organizationId);

        $usage->increment('calendar_generations_used', $amount);

        return $usage->refresh();
    }

    public function incrementTextTokens(int $organizationId, int $tokens): UsageLog
    {
        $usage = $this->findOrCreateCurrentPeriod($organizationId);

        $usage->increment('text_tokens_used', $tokens);

        return $usage->refresh();
    }

    public function incrementImagesGenerated(int $organizationId, int $amount = 1): UsageLog
    {
        $usage = $this->findOrCreateCurrentPeriod($organizationId);

        $usage->increment('images_generated', $amount);

        return $usage->refresh();
    }

    public function initializeForOrganization(int $organizationId): UsageLog
    {
        $now = Carbon::now();

        return $this->model->create([
            'organization_id' => $organizationId,
            'period_start' => $now->copy()->startOfMonth(),
            'period_end' => $now->copy()->endOfMonth(),
            'calendar_generations_used' => 0,
            'text_tokens_used' => 0,
            'images_generated' => 0,
            'overage_tokens' => 0,
            'overage_images' => 0,
            'overage_cost' => 0,
        ]);
    }
}
