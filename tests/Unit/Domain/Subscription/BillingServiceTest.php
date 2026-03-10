<?php

declare(strict_types=1);

use App\Domain\Organization\Contracts\OrganizationRepositoryInterface;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Contracts\UsageLogRepositoryInterface;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\UsageLog;
use App\Domain\Subscription\Services\BillingService;

beforeEach(function () {
    $this->orgRepo = Mockery::mock(OrganizationRepositoryInterface::class);
    $this->usageLogRepo = Mockery::mock(UsageLogRepositoryInterface::class);
    $this->billing = new BillingService($this->orgRepo, $this->usageLogRepo);
});

afterEach(fn () => Mockery::close());

describe('getEffectiveLimits', function () {
    it('returns plan limits when no custom limits', function () {
        $plan = new Plan([
            'max_brands' => 5,
            'max_users' => 10,
            'monthly_calendar_generations' => 20,
            'monthly_text_tokens' => 200_000,
            'monthly_images' => 100,
        ]);

        $org = new Organization(['name' => 'Test', 'custom_limits' => null]);
        $org->setRelation('plan', $plan);

        $limits = $this->billing->getEffectiveLimits($org);

        expect($limits)->toMatchArray([
            'max_brands' => 5,
            'max_users' => 10,
            'monthly_calendars' => 20,
            'monthly_tokens' => 200_000,
            'monthly_images' => 100,
        ]);
    });

    it('returns defaults when no plan assigned', function () {
        $org = new Organization(['name' => 'Test', 'custom_limits' => null]);
        $org->setRelation('plan', null);

        $limits = $this->billing->getEffectiveLimits($org);

        expect($limits)->toMatchArray([
            'max_brands' => 1,
            'max_users' => 1,
            'monthly_calendars' => 3,
            'monthly_tokens' => 50_000,
            'monthly_images' => 20,
        ]);
    });

    it('merges custom limits over plan limits', function () {
        $plan = new Plan([
            'max_brands' => 5,
            'max_users' => 10,
            'monthly_calendar_generations' => 20,
            'monthly_text_tokens' => 200_000,
            'monthly_images' => 100,
        ]);

        $org = new Organization([
            'name' => 'Test',
            'custom_limits' => ['max_brands' => 99, 'monthly_images' => 500],
        ]);
        $org->setRelation('plan', $plan);

        $limits = $this->billing->getEffectiveLimits($org);

        expect($limits['max_brands'])->toBe(99);
        expect($limits['monthly_images'])->toBe(500);
        // Non-overridden values come from plan
        expect($limits['max_users'])->toBe(10);
    });
});

describe('checkUsageLimit', function () {
    it('allows when no usage record exists', function () {
        $plan = new Plan([
            'max_brands' => 5,
            'max_users' => 10,
            'monthly_calendar_generations' => 10,
            'monthly_text_tokens' => 100_000,
            'monthly_images' => 50,
        ]);

        $org = new Organization(['name' => 'Test', 'custom_limits' => null]);
        $org->id = 1;
        $org->setRelation('plan', $plan);

        $this->orgRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($org);
        $this->usageLogRepo->shouldReceive('findCurrentPeriod')->with(1)->once()->andReturn(null);

        expect($this->billing->checkUsageLimit(1, 'calendars'))->toBeTrue();
    });

    it('allows when under limit', function () {
        $plan = new Plan([
            'max_brands' => 5,
            'max_users' => 10,
            'monthly_calendar_generations' => 10,
            'monthly_text_tokens' => 100_000,
            'monthly_images' => 50,
        ]);

        $org = new Organization(['name' => 'Test', 'custom_limits' => null]);
        $org->id = 1;
        $org->setRelation('plan', $plan);

        $usage = new UsageLog([
            'calendar_generations_used' => 5,
            'text_tokens_used' => 50_000,
            'images_generated' => 20,
        ]);

        $this->orgRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($org);
        $this->usageLogRepo->shouldReceive('findCurrentPeriod')->with(1)->once()->andReturn($usage);

        expect($this->billing->checkUsageLimit(1, 'calendars'))->toBeTrue();
    });

    it('rejects when at limit without overage', function () {
        $plan = new Plan([
            'max_brands' => 5,
            'max_users' => 10,
            'monthly_calendar_generations' => 10,
            'monthly_text_tokens' => 100_000,
            'monthly_images' => 50,
            'allows_overage' => false,
        ]);

        $org = new Organization(['name' => 'Test', 'custom_limits' => null]);
        $org->id = 1;
        $org->setRelation('plan', $plan);

        $usage = new UsageLog([
            'calendar_generations_used' => 10,
            'text_tokens_used' => 50_000,
            'images_generated' => 20,
        ]);

        $this->orgRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($org);
        $this->usageLogRepo->shouldReceive('findCurrentPeriod')->with(1)->once()->andReturn($usage);

        expect($this->billing->checkUsageLimit(1, 'calendars'))->toBeFalse();
    });

    it('allows overage when plan permits', function () {
        $plan = new Plan([
            'max_brands' => 5,
            'max_users' => 10,
            'monthly_calendar_generations' => 10,
            'monthly_text_tokens' => 100_000,
            'monthly_images' => 50,
            'allows_overage' => true,
        ]);

        $org = new Organization(['name' => 'Test', 'custom_limits' => null]);
        $org->id = 1;
        $org->setRelation('plan', $plan);

        $usage = new UsageLog([
            'calendar_generations_used' => 15,
            'text_tokens_used' => 50_000,
            'images_generated' => 20,
        ]);

        $this->orgRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($org);
        $this->usageLogRepo->shouldReceive('findCurrentPeriod')->with(1)->once()->andReturn($usage);

        expect($this->billing->checkUsageLimit(1, 'calendars'))->toBeTrue();
    });

    it('allows unlimited (-1) limits', function () {
        $plan = new Plan([
            'max_brands' => -1,
            'max_users' => -1,
            'monthly_calendar_generations' => -1,
            'monthly_text_tokens' => -1,
            'monthly_images' => -1,
        ]);

        $org = new Organization(['name' => 'Test', 'custom_limits' => null]);
        $org->id = 1;
        $org->setRelation('plan', $plan);

        $usage = new UsageLog([
            'calendar_generations_used' => 999,
            'text_tokens_used' => 999_999,
            'images_generated' => 999,
        ]);

        $this->orgRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($org);
        $this->usageLogRepo->shouldReceive('findCurrentPeriod')->with(1)->once()->andReturn($usage);

        expect($this->billing->checkUsageLimit(1, 'calendars'))->toBeTrue();
    });
});

describe('canGenerate', function () {
    it('returns true when calendar quota available', function () {
        $plan = new Plan([
            'max_brands' => 5,
            'max_users' => 10,
            'monthly_calendar_generations' => 10,
            'monthly_text_tokens' => 100_000,
            'monthly_images' => 50,
        ]);

        $org = new Organization(['name' => 'Test', 'custom_limits' => null]);
        $org->id = 1;
        $org->setRelation('plan', $plan);

        $this->orgRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($org);
        $this->usageLogRepo->shouldReceive('findCurrentPeriod')->with(1)->once()->andReturn(null);

        expect($this->billing->canGenerate(1))->toBeTrue();
    });
});
