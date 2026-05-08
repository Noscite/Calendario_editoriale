<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preparazione colonne per PR-WIZARD-2 (Project wizard 4-step) — create ora
 * per ridurre il count di migration future. NON popolate in PR-WIZARD-1.
 *
 *  - personas_source:         enum string — 'generated_new' / 'reused_from:<id>' / 'adapted_from:<id>'
 *  - personas_ai_suggestion:  json — snapshot della raccomandazione Sonnet (similarity, reasoning)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('personas_source', 50)->nullable()->after('buyer_personas');
            $table->json('personas_ai_suggestion')->nullable()->after('personas_source');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['personas_source', 'personas_ai_suggestion']);
        });
    }
};
