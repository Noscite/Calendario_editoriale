<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge generation_metadata (JSON) ai post per tracciare al momento
 * della creazione: modello AI usato, versione del prompt (git commit),
 * strategy plan applicato (angle, hook_type, persona_target, cta_goal),
 * token usage. Letto solo per debugging — niente index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('generation_metadata')->nullable()->after('call_to_action');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('generation_metadata');
        });
    }
};
