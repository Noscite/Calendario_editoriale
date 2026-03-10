<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Contracts;

interface UsageTrackerInterface
{
    /**
     * Registra l'utilizzo di una generazione di calendario.
     */
    public function trackCalendarGeneration(int $organizationId, int $amount = 1): void;

    /**
     * Registra l'utilizzo di token di testo.
     */
    public function trackTextTokens(int $organizationId, int $tokens): void;

    /**
     * Registra la generazione di un'immagine.
     */
    public function trackImageGeneration(int $organizationId, int $amount = 1): void;

    /**
     * Ottieni il riepilogo di utilizzo per l'organizzazione corrente.
     *
     * @return array{
     *     calendars_used: int,
     *     calendars_limit: int,
     *     tokens_used: int,
     *     tokens_limit: int,
     *     images_used: int,
     *     images_limit: int,
     *     period_start: string,
     *     period_end: string,
     * }
     */
    public function getUsageSummary(int $organizationId): array;

    /**
     * Ottieni l'utilizzo per un periodo specifico (admin).
     */
    public function getUsageForPeriod(int $organizationId, ?string $period = null): array;

    /**
     * Verifica se l'organizzazione ha quota disponibile per un tipo specifico.
     *
     * @param  string  $type  calendars|tokens|images
     */
    public function checkQuota(int $organizationId, string $type): bool;
}
