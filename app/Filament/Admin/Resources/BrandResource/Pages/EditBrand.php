<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Pages;

use App\Filament\Admin\Concerns\RepackTerritoryMeta;
use App\Filament\Admin\Resources\BrandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBrand extends EditRecord
{
    use RepackTerritoryMeta;

    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Estrai territory_meta nei campi virtuali del form per pre-popolarli.
        $meta = $data['territory_meta'] ?? [];
        $data['territory_region'] = $meta['region'] ?? null;
        $data['territory_municipality_istat'] = $meta['municipality_istat'] ?? null;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->repackTerritoryMeta($data);
    }
}
