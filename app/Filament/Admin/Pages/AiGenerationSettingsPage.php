<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Models\AiGenerationSetting;
use App\Domain\Generation\Services\AiGenerationSettingsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Admin: parametri di generazione AI (modello, temperature, max_tokens,
 * top_p/top_k, prompt caching) per step della pipeline.
 * Ambito "Globale" (brand_id NULL) = default per tutta la piattaforma.
 * Ambito "Brand" = override che vince sul globale, campo per campo
 * (un campo lasciato vuoto eredita dal globale, non dall'hardcoded).
 */
class AiGenerationSettingsPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Parametri Generazione AI';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?string $title = 'Parametri di generazione AI';
    protected static ?int $navigationSort = 51;

    public function getView(): string
    {
        return 'filament.admin.pages.ai-generation-settings';
    }

    // '' = ambito globale. Stringa (non ?int) per il binding wire:model del
    // <select>: Livewire non gestisce bene un cast a null su proprietà tipate.
    public string $selectedBrandId = '';
    public array $formData = [];

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function availableBrands()
    {
        return Brand::withoutGlobalScope('organization')->orderBy('name')->get();
    }

    public function updatedSelectedBrandId(): void
    {
        $this->loadSettings();
    }

    private function brandId(): ?int
    {
        return $this->selectedBrandId !== '' ? (int) $this->selectedBrandId : null;
    }

    public function loadSettings(): void
    {
        $rows = AiGenerationSetting::where('brand_id', $this->brandId())
            ->get()
            ->keyBy('step');

        $this->formData = [];
        foreach (AiGenerationSettingsService::steps() as $step => $label) {
            $row = $rows->get($step);
            $this->formData[$step] = [
                'model'                   => $row?->model,
                'temperature'             => $row?->temperature,
                'max_tokens'              => $row?->max_tokens,
                'top_p'                   => $row?->top_p,
                'top_k'                   => $row?->top_k,
                'prompt_caching_enabled'  => $row?->prompt_caching_enabled === null
                    ? ''
                    : ($row->prompt_caching_enabled ? '1' : '0'),
            ];
        }
    }

    public function save(): void
    {
        foreach ($this->formData as $step => $fields) {
            $isEmpty = ($fields['model'] ?? '') === ''
                && ($fields['temperature'] ?? '') === ''
                && ($fields['max_tokens'] ?? '') === ''
                && ($fields['top_p'] ?? '') === ''
                && ($fields['top_k'] ?? '') === ''
                && ($fields['prompt_caching_enabled'] ?? '') === '';

            if ($isEmpty) {
                AiGenerationSetting::where('brand_id', $this->brandId())->where('step', $step)->delete();
                app(AiGenerationSettingsService::class)->forgetCache($this->brandId(), $step);
                continue;
            }

            AiGenerationSetting::updateOrCreate(
                ['brand_id' => $this->brandId(), 'step' => $step],
                [
                    'model'                  => $fields['model'] !== '' ? $fields['model'] : null,
                    'temperature'            => $fields['temperature'] !== '' ? $fields['temperature'] : null,
                    'max_tokens'             => $fields['max_tokens'] !== '' ? $fields['max_tokens'] : null,
                    'top_p'                  => $fields['top_p'] !== '' ? $fields['top_p'] : null,
                    'top_k'                  => $fields['top_k'] !== '' ? $fields['top_k'] : null,
                    'prompt_caching_enabled' => $fields['prompt_caching_enabled'] === ''
                        ? null
                        : (bool) $fields['prompt_caching_enabled'],
                ]
            );
            app(AiGenerationSettingsService::class)->forgetCache($this->brandId(), $step);
        }

        Notification::make()->title('Parametri salvati con successo')->success()->send();
        $this->loadSettings();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'superuser';
    }
}
