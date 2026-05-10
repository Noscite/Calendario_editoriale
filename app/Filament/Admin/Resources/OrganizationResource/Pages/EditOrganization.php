<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OrganizationResource\Pages;

use App\Filament\Admin\Concerns\RepackTerritoryMeta;
use App\Filament\Admin\Resources\OrganizationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    use RepackTerritoryMeta;

    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $meta = $data['default_territory_meta'] ?? [];
        $data['default_territory_region'] = $meta['region'] ?? null;
        $data['default_territory_municipality_istat'] = $meta['municipality_istat'] ?? null;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->repackTerritoryMeta(
            $data,
            verticalField: 'default_vertical',
            regionField: 'default_territory_region',
            municipalityField: 'default_territory_municipality_istat',
            metaField: 'default_territory_meta',
        );
    }
}
