<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\Plan;
use App\Filament\Admin\Resources\PlanResource\Pages\EditPlan;
use Livewire\Livewire;

beforeEach(function () {
    [$this->superAdmin] = createAuthenticatedUser(['role' => 'superuser']);
});

it('exposes post_credit_price_eur on the plan edit form and saves it', function () {
    $plan = createPlan(['name' => 'edit-test-plan']);

    $this->actingAs($this->superAdmin);

    Livewire::test(EditPlan::class, ['record' => $plan->id])
        ->assertFormFieldExists('post_credit_price_eur')
        ->fillForm(['post_credit_price_eur' => 3.50])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $plan->refresh()->post_credit_price_eur)->toBe(3.50);
});

it('no longer exposes the legacy quota fields (tracked but never enforced pre-wallet)', function () {
    $plan = createPlan(['name' => 'legacy-fields-test-plan']);

    $this->actingAs($this->superAdmin);

    $component = Livewire::test(EditPlan::class, ['record' => $plan->id]);

    $component->assertFormFieldDoesNotExist('monthly_calendar_generations');
    $component->assertFormFieldDoesNotExist('monthly_text_tokens');
    $component->assertFormFieldDoesNotExist('monthly_images');
});
