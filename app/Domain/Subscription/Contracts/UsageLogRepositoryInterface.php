<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Contracts;

use App\Domain\Subscription\Models\UsageLog;

interface UsageLogRepositoryInterface
{
    /**
     * Trova il record di usage per organizzazione nel periodo corrente.
     */
    public function findCurrentPeriod(int $organizationId): ?UsageLog;

    /**
     * Trova o crea il record di usage per il periodo corrente.
     */
    public function findOrCreateCurrentPeriod(int $organizationId): UsageLog;

    /**
     * Ottieni statistiche di utilizzo per un'organizzazione in un periodo specifico.
     */
    public function getStatsByOrganization(int $organizationId, ?string $period = null): ?UsageLog;

    /**
     * Incrementa le generazioni di calendario usate.
     */
    public function incrementCalendarGenerations(int $organizationId, int $amount = 1): UsageLog;

    /**
     * Incrementa i token di testo usati.
     */
    public function incrementTextTokens(int $organizationId, int $tokens): UsageLog;

    /**
     * Incrementa le immagini generate.
     */
    public function incrementImagesGenerated(int $organizationId, int $amount = 1): UsageLog;

    /**
     * Crea un nuovo record di tracking per una nuova organizzazione.
     */
    public function initializeForOrganization(int $organizationId): UsageLog;
}
