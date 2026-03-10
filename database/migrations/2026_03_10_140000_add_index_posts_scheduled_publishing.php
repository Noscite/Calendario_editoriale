<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge indice composito su posts per ottimizzare la query dello scheduler.
 *
 * Lo scheduler legge ogni minuto tutti i post dove:
 *   scheduled_date = oggi
 *   AND SUBSTRING(scheduled_time, 1, 5) IN [HH:MM ± 2 min]
 *   AND publication_status = 'scheduled'
 *
 * L'indice (scheduled_date, publication_status, scheduled_time) copre
 * le tre colonne usate nel WHERE e riduce il full-table-scan a zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Aggiungi la colonna publishing se non esiste già nel enum
            // (il valore è gestito dall'applicazione — nessuna modifica DDL al tipo)

            $table->index(
                ['scheduled_date', 'publication_status', 'scheduled_time'],
                'idx_posts_scheduled_publishing'
            );
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('idx_posts_scheduled_publishing');
        });
    }
};
