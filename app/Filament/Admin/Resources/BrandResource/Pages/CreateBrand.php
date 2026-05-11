<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Pages;

use App\Filament\Admin\Concerns\RepackTerritoryMeta;
use App\Filament\Admin\Resources\BrandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    use RepackTerritoryMeta;

    protected static string $resource = BrandResource::class;

    /** @var list<string>|null */
    private ?array $pendingDeontologicalConstraints = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingDeontologicalConstraints = array_key_exists('deontological_constraints', $data)
            ? (is_array($data['deontological_constraints']) ? array_values($data['deontological_constraints']) : [])
            : null;
        unset($data['deontological_constraints']);

        return $this->repackTerritoryMeta($data);
    }

    protected function afterCreate(): void
    {
        if ($this->pendingDeontologicalConstraints !== null) {
            $this->record->syncDeontologicalConstraints($this->pendingDeontologicalConstraints);
        }
    }
}
