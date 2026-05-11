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

    /** @var list<string>|null */
    private ?array $pendingDeontologicalConstraints = null;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $meta = $data['territory_meta'] ?? [];
        $data['territory_region'] = $meta['region'] ?? null;
        $data['territory_municipality_istat'] = $meta['municipality_istat'] ?? null;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // deontological_constraints è gestito via pivot table: rimuovi dal payload
        // del Brand (chiave non corrispondente a colonna) e sincronizza dopo save.
        $this->pendingDeontologicalConstraints = array_key_exists('deontological_constraints', $data)
            ? (is_array($data['deontological_constraints']) ? array_values($data['deontological_constraints']) : [])
            : null;
        unset($data['deontological_constraints']);

        return $this->repackTerritoryMeta($data);
    }

    protected function afterSave(): void
    {
        if ($this->pendingDeontologicalConstraints !== null) {
            $this->record->syncDeontologicalConstraints($this->pendingDeontologicalConstraints);
        }
    }
}
