<?php

declare(strict_types=1);

namespace App\Domain\Review\Contracts;

use App\Domain\Brand\Models\Brand;

interface OntologyBootstrapServiceInterface
{
    /**
     * Genera l'ontologia dei topic per il brand e la persiste.
     *
     * @return array<int, array{id:string,label:string,description:string}>
     */
    public function bootstrapForBrand(Brand $brand): array;
}
