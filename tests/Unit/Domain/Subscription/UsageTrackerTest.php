<?php

declare(strict_types=1);

use App\Domain\Organization\Contracts\OrganizationRepositoryInterface;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Contracts\BillingServiceInterface;
use App\Domain\Subscription\Contracts\UsageLogRepositoryInterface;
use App\Domain\Subscription\Models\UsageLog;
use App\Domain\Subscription\Services\UsageTracker;

beforeEach(function () {
    $this->usageLogRepo = Mockery::mock(UsageLogRepositoryInterface::class);
    $this->orgRepo = Mockery::mock(OrganizationRepositoryInterface::class);
    $this->billing = Mockery::mock(BillingServiceInterface::class);
    $this->tracker = new UsageTracker($this->usageLogRepo, $this->orgRepo, $this->billing);
});

afterEach(fn () => Mockery::close());

describe('trackCalendarGeneration', function () {
    it('increments calendar generation counter', function () {
        $this->usageLogRepo
            ->shouldReceive('incrementCalendarGenerations')
            ->with(1, 1)
            ->once();

        $this->tracker->trackCalendarGeneration(1);

        expect(true)->toBeTrue();
    });

    it('increments by custom amount', function () {
        $this->usageLogRepo
            ->shouldReceive('incrementCalendarGenerations')
            ->with(1, 5)
            ->once();

        $this->tracker->trackCalendarGeneration(1, 5);

        expect(true)->toBeTrue();
    });
});

describe('trackTextTokens', function () {
    it('increments token counter', function () {
        $this->usageLogRepo
            ->shouldReceive('incrementTextTokens')
            ->with(1, 1500)
            ->once();

        $this->tracker->trackTextTokens(1, 1500);

        expect(true)->toBeTrue();
    });
});

describe('trackImageGeneration', function () {
    it('increments image counter', function () {
        $this->usageLogRepo
            ->shouldReceive('incrementImagesGenerated')
            ->with(1, 1)
            ->once();

        $this->tracker->trackImageGeneration(1);

        expect(true)->toBeTrue();
    });
});

describe('getUsageSummary', function () {
    it('returns usage summary with limits', function () {
        $org = new Organization(['name' => 'Test']);
        $org->id = 1;

        $usage = new UsageLog([
            'calendar_generations_used' => 3,
            'text_tokens_used' => 25000,
            'images_generated' => 10,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
        ]);

        $this->orgRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($org);

        $this->billing->shouldReceive('getEffectiveLimits')->with($org)->once()->andReturn([
            'monthly_calendars' => 10,
            'monthly_tokens' => 100_000,
            'monthly_images' => 50,
        ]);

        $this->usageLogRepo
            ->shouldReceive('findOrCreateCurrentPeriod')
            ->with(1)
            ->once()
            ->andReturn($usage);

        $result = $this->tracker->getUsageSummary(1);

        expect($result)->toMatchArray([
            'calendars_used' => 3,
            'calendars_limit' => 10,
            'tokens_used' => 25000,
            'tokens_limit' => 100_000,
            'images_used' => 10,
            'images_limit' => 50,
        ]);
        expect($result)->toHaveKeys(['period_start', 'period_end']);
    });
});

describe('getUsageForPeriod', function () {
    it('returns empty usage when no records exist', function () {
        $org = new Organization(['name' => 'Test']);
        $org->id = 1;

        $this->orgRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($org);

        $this->billing->shouldReceive('getEffectiveLimits')->with($org)->once()->andReturn([
            'monthly_calendars' => 10,
            'monthly_tokens' => 100_000,
            'monthly_images' => 50,
        ]);

        $this->usageLogRepo
            ->shouldReceive('getStatsByOrganization')
            ->with(1, '2026-02')
            ->once()
            ->andReturn(null);

        $result = $this->tracker->getUsageForPeriod(1, '2026-02');

        expect($result['calendars_used'])->toBe(0);
        expect($result['tokens_used'])->toBe(0);
        expect($result['images_used'])->toBe(0);
        expect($result['overage_cost'])->toBe('0.00');
    });
});

describe('checkQuota', function () {
    it('delegates to billing service', function () {
        $this->billing
            ->shouldReceive('checkUsageLimit')
            ->with(1, 'calendars')
            ->once()
            ->andReturn(true);

        $result = $this->tracker->checkQuota(1, 'calendars');

        expect($result)->toBeTrue();
    });

    it('returns false when over limit', function () {
        $this->billing
            ->shouldReceive('checkUsageLimit')
            ->with(1, 'images')
            ->once()
            ->andReturn(false);

        $result = $this->tracker->checkQuota(1, 'images');

        expect($result)->toBeFalse();
    });
});
