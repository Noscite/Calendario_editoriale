<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Concerns;

trait RepackTerritoryMeta
{
    /**
     * Compatta i campi virtuali del form (territory_region / territory_municipality_istat)
     * nel JSON `territory_meta` del Brand. Rimuove i campi virtuali per evitare
     * mass-assignment di colonne non esistenti.
     */
    protected function repackTerritoryMetaForBrand(array $data): array
    {
        $meta = [];
        $vertical = $data['vertical'] ?? null;

        if ($vertical === 'unpli_regional' && ! empty($data['territory_region'])) {
            $meta['region'] = $data['territory_region'];
        } elseif ($vertical === 'pro_loco' && ! empty($data['territory_municipality_istat'])) {
            $meta['municipality_istat'] = $data['territory_municipality_istat'];
        }

        $data['territory_meta'] = empty($meta) ? null : $meta;

        // Rimuovi i campi virtuali del form così non finiscono nel mass assignment
        unset($data['territory_region'], $data['territory_municipality_istat']);

        return $data;
    }
}
