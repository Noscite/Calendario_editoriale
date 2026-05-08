<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wizard Brand 5-step (PR-WIZARD-1) — colonne nuove additive.
 *
 *  - tagline:                 una frase identità (Step 1)
 *  - founder:                 json {name, role, bio_short} (Step 3, opzionale per corporate)
 *  - narrative_assets:        json [{type, name, details}] — LISTA CHIUSA per anti-invenzione (Step 3)
 *  - default_content_pillars: json [{name, description}] — libreria pillar del Brand (Step 4)
 *  - forbidden_topics:        json [string] — argomenti vietati (Step 4, va in ASSET DA NON CITARE)
 *
 * Tutti nullable. Niente DROP, niente RENAME. Niente data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('tagline', 180)->nullable()->after('name');
            $table->json('founder')->nullable()->after('voice_examples');
            $table->json('narrative_assets')->nullable()->after('founder');
            $table->json('default_content_pillars')->nullable()->after('narrative_assets');
            $table->json('forbidden_topics')->nullable()->after('default_content_pillars');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'tagline',
                'founder',
                'narrative_assets',
                'default_content_pillars',
                'forbidden_topics',
            ]);
        });
    }
};
