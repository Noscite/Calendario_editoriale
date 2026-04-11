<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandApiKeyService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BrandApiSettings extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'API & Integrazioni';
    protected static ?string $title           = 'Chiavi API per Brand';
    protected static ?int    $navigationSort  = 90;

    public function getView(): string
    {
        return 'filament.admin.pages.brand-api-settings';
    }

    public ?int $selectedBrandId = null;
    public array $formData = [];

    public function mount(): void
    {
        $brand = $this->availableBrands()->first();
        if ($brand) {
            $this->selectedBrandId = $brand->id;
            $this->loadKeys();
        }
    }

    public function availableBrands()
    {
        return Brand::orderBy('name')->get();
    }

    public function loadKeys(): void
    {
        if (! $this->selectedBrandId) {
            return;
        }

        $brand           = Brand::findOrFail($this->selectedBrandId);
        $this->formData  = app(BrandApiKeyService::class)->getAll($brand);
    }

    public function updatedSelectedBrandId(): void
    {
        $this->loadKeys();
    }

    public function save(): void
    {
        if (! $this->selectedBrandId) {
            return;
        }

        $brand = Brand::findOrFail($this->selectedBrandId);
        app(BrandApiKeyService::class)->saveMany($brand, $this->formData);

        Notification::make()
            ->title('Chiavi salvate con successo')
            ->success()
            ->send();
    }
}
