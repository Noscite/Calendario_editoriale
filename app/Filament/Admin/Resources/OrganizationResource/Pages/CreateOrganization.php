<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OrganizationResource\Pages;

use App\Filament\Admin\Concerns\RepackTerritoryMeta;
use App\Filament\Admin\Resources\OrganizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    use RepackTerritoryMeta;

    protected static string $resource = OrganizationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
