<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campi minimali per il flow Campagna AI:
 * - pillar:  tema contenutistico scelto dall'utente nel modal (es. "Educational")
 * - generation_error: messaggio errore se la generazione AI fallisce (CampaignStatus
 *   torna a Draft, l'utente vede il motivo nello storico)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('pillar', 255)->nullable()->after('brief');
            $table->text('generation_error')->nullable()->after('pillar');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['pillar', 'generation_error']);
        });
    }
};
