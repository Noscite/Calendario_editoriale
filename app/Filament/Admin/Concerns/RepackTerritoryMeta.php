<?php

declare(strict_types=1);

namespace App\Filament\Admin\Concerns;

trait RepackTerritoryMeta
{
    /**
     * Compatta i campi virtuali del form (region/municipality) nel JSON `territory_meta`.
     * Riusabile per Brand (vertical / territory_meta) e Organization (default_vertical / default_territory_meta).
     * Rimuove i campi virtuali per evitare mass-assignment di colonne non esistenti.
     */
    protected function repackTerritoryMeta(
        array $data,
        string $verticalField = 'vertical',
        string $regionField = 'territory_region',
        string $municipalityField = 'territory_municipality_istat',
        string $metaField = 'territory_meta',
    ): array {
        $meta = [];
        $vertical = $data[$verticalField] ?? null;

        if ($vertical === 'unpli_regional' && ! empty($data[$regionField])) {
            $meta['region'] = $data[$regionField];
        } elseif ($vertical === 'pro_loco' && ! empty($data[$municipalityField])) {
            $meta['municipality_istat'] = $data[$municipalityField];
        }

        $data[$metaField] = empty($meta) ? null : $meta;

        unset($data[$regionField], $data[$municipalityField]);

        return $data;
    }
}
