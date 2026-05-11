<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup delle Campaign create dal PR precedente (creazione standalone via
 * /campaign/new ora rimosso) che non hanno mai prodotto post.
 *
 * Regola: ogni Campaign in stato NON-Draft (planning/active/completed) ma
 * SENZA posts in tabella posts → marcata 'archived' con generation_error
 * informativo, per dare contesto allo storico.
 *
 * Le Draft restano intatte: vengono raccolte dal command schedulato
 * 'campaigns:cleanup-stale-drafts' che le elimina se non promosse entro 24h.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $affected = DB::table('campaigns')
            ->whereNotIn('status', ['draft', 'archived'])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('posts')
                    ->whereColumn('posts.campaign_id', 'campaigns.id');
            })
            ->update([
                'status'           => 'archived',
                'generation_error' => 'Archiviata automaticamente: creata via /campaign/new prima del refactor unify-campaign (PR precedente), nessun post mai generato.',
                'updated_at'       => now(),
            ]);

        Log::info("[CLEANUP] Archiviate {$affected} campaign legacy senza post.");
    }

    public function down(): void
    {
        // No rollback: dato lo scope (cleanup di test data legacy), non vale
        // la pena ripristinare lo stato precedente.
    }
};
