<?php

declare(strict_types=1);

use App\Domain\Generation\Models\AiGenerationSetting;
use App\Domain\Generation\Services\AiGenerationSettingsService;
use App\Filament\Admin\Pages\AiGenerationSettingsPage;
use Livewire\Livewire;

beforeEach(function () {
    [$this->superAdmin, $org] = createAuthenticatedUser(['role' => 'superuser']);
    $this->brand = createBrand($org);
});

it('renders with default (empty) settings for all steps', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(AiGenerationSettingsPage::class)
        ->assertSuccessful()
        ->assertSee('Valori ereditati di default')
        ->assertSee('claude-opus-4-8'); // default copy model, dalla tabella riepilogo
});

it('saves a global override and reloads it', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(AiGenerationSettingsPage::class)
        ->set('formData.' . AiGenerationSettingsService::STEP_COPY . '.temperature', '0.42')
        ->call('save')
        ->assertSuccessful();

    $row = AiGenerationSetting::whereNull('brand_id')->where('step', AiGenerationSettingsService::STEP_COPY)->first();
    expect($row)->not->toBeNull();
    expect((float) $row->temperature)->toBe(0.42);
});

it('saves a per-brand override without touching the global row', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(AiGenerationSettingsPage::class)
        ->set('selectedBrandId', (string) $this->brand->id)
        ->set('formData.' . AiGenerationSettingsService::STEP_STRATEGY . '.model', 'claude-sonnet-4-6')
        ->call('save')
        ->assertSuccessful();

    $brandRow = AiGenerationSetting::where('brand_id', $this->brand->id)
        ->where('step', AiGenerationSettingsService::STEP_STRATEGY)->first();
    expect($brandRow?->model)->toBe('claude-sonnet-4-6');

    $globalRow = AiGenerationSetting::whereNull('brand_id')
        ->where('step', AiGenerationSettingsService::STEP_STRATEGY)->first();
    expect($globalRow)->toBeNull();
});

it('deletes the row when all fields are cleared back to empty', function () {
    $this->actingAs($this->superAdmin);
    AiGenerationSetting::create(['brand_id' => null, 'step' => AiGenerationSettingsService::STEP_COPY, 'temperature' => 0.3]);

    Livewire::test(AiGenerationSettingsPage::class)
        ->set('formData.' . AiGenerationSettingsService::STEP_COPY . '.temperature', '')
        ->call('save')
        ->assertSuccessful();

    expect(AiGenerationSetting::whereNull('brand_id')->where('step', AiGenerationSettingsService::STEP_COPY)->exists())->toBeFalse();
});
