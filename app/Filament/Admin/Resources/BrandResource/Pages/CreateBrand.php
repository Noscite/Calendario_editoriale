<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Pages;

use App\Filament\Admin\Resources\BrandResource;
use App\Filament\Admin\Resources\BrandResource\Concerns\RepackTerritoryMeta;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    use RepackTerritoryMeta;

    protected static string $resource = BrandResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->repackTerritoryMetaForBrand($data);
    }
}
