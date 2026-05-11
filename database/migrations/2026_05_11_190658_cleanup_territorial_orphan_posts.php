<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup dei post territoriali generati per piattaforme NON selezionate
 * nel project (bug fixed nel commit precedente "respect project.platforms").
 *
 * Strategia: per ogni project che ha generato post territoriali (source
 * 'territorial' o 'territorial_monthly_digest'), identifica i post la cui
 * platform NON appartiene a project.platforms[] → cancella.
 *
 * Difensivo: se project.platforms è null/empty, NON tocca i post (potrebbe
 * trattarsi di project legacy senza vincolo platforms ancora settato).
 *
 * Idempotente. Loggato con count per project.
 */
return new class extends Migration
{
    public function up(): void
    {
        $deletedTotal = 0;

        $projects = DB::table('projects')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('posts')
                  ->whereColumn('posts.project_id', 'projects.id')
                  ->whereIn(
                      DB::raw("posts.generation_metadata->>'source'"),
                      ['territorial', 'territorial_monthly_digest']
                  );
            })
            ->select('id', 'platforms')
            ->get();

        foreach ($projects as $project) {
            $platformsJson = $project->platforms;
            $allowedPlatforms = $platformsJson ? json_decode($platformsJson, true) : null;

            if (empty($allowedPlatforms) || ! is_array($allowedPlatforms)) {
                Log::warning("[CLEANUP] Project {$project->id}: no platforms set, skipping (defensive)");
                continue;
            }

            $count = DB::table('posts')
                ->where('project_id', $project->id)
                ->whereIn(
                    DB::raw("generation_metadata->>'source'"),
                    ['territorial', 'territorial_monthly_digest']
                )
                ->whereNotIn('platform', $allowedPlatforms)
                ->delete();

            if ($count > 0) {
                Log::info("[CLEANUP] Project {$project->id}: deleted {$count} orphan territorial posts (allowed=" . implode(',', $allowedPlatforms) . ")");
                $deletedTotal += $count;
            }
        }

        Log::info("[CLEANUP] Total orphan territorial posts deleted: {$deletedTotal}");
    }

    public function down(): void
    {
        // Cleanup di dati non recuperabili — no rollback.
    }
};
