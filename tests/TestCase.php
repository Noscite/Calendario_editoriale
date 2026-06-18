<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // GUARD ANTI-PRODUZIONE: aborta se i test puntano a un DB che non è
        // quello di test (incidente già avvenuto: config:cache aveva congelato
        // DB_DATABASE di prod e i test Unit, che committano senza
        // RefreshDatabase, hanno inquinato il DB reale).
        static::guardAgainstProductionDatabase();

        // Disabilita il rate limiting in tutti i test.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /**
     * Aborta l'intera suite se il database target non contiene 'test'.
     */
    public static function guardAgainstProductionDatabase(): void
    {
        $db = config('database.connections.' . config('database.default') . '.database');

        if (! str_contains((string) $db, 'test')) {
            throw new \RuntimeException(
                "ABORT SICUREZZA: i test puntano al database '{$db}' che non contiene 'test'. "
                . "Probabile config di produzione cachata. Esegui `php artisan config:clear`, "
                . "verifica phpunit.xml, e rilancia. (Questo guard previene la pollution del DB prod.)"
            );
        }
    }
}
