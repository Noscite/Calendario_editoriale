<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Contracts;

use App\Domain\Territorial\DTOs\EventPayload;

interface TerritorialDataProviderInterface
{
    /** Identificativo della sorgente (es. 'e015'). */
    public function source(): string;

    /**
     * Lista degli ID degli eventi disponibili (slim, paginati).
     * @return array<int|string>
     */
    public function listEventIds(int $limit = 100, int $offset = 0): array;

    /**
     * Dettaglio completo di un evento.
     */
    public function fetchEvent(int|string $externalId): ?EventPayload;
}
