<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia la relazione Campaign ↔ Project ↔ Post:
 *
 * - Prima: projects.campaign_id (un project = una campagna)
 * - Adesso: posts.campaign_id (un project ospita N campagne, ogni post sa chi l'ha
 *   generato). La colonna posts.campaign_id esisteva già in schema (FK +
 *   index pre-esistente nullable cascade SET NULL): aggiungiamo solo data
 *   migration + drop di projects.campaign_id (introdotto dal PR
 *   feat/campaigns-attachments-and-mcp).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill: se ci sono Project con campaign_id valorizzato dal PR
        //    precedente, propaga sui post non ancora linkati.
        if (Schema::hasColumn('projects', 'campaign_id')) {
            $linkedProjects = DB::table('projects')
                ->whereNotNull('campaign_id')
                ->select('id', 'campaign_id')
                ->get();

            foreach ($linkedProjects as $project) {
                DB::table('posts')
                    ->where('project_id', $project->id)
                    ->whereNull('campaign_id')
                    ->update(['campaign_id' => $project->campaign_id]);
            }
        }

        // 2. Drop projects.campaign_id (non più source of truth).
        if (Schema::hasColumn('projects', 'campaign_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropConstrainedForeignId('campaign_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('brand_id')
                ->constrained('campaigns')
                ->nullOnDelete();
        });
    }
};
