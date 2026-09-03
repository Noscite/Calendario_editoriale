<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_settings', function (Blueprint $table) {
            $table->id();
            // NULL = default globale. Valorizzato = override per singolo brand.
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('step', 50);
            $table->string('model', 100)->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->decimal('top_p', 3, 2)->nullable();
            $table->unsignedInteger('top_k')->nullable();
            $table->boolean('prompt_caching_enabled')->nullable();
            $table->timestamps();

            $table->index('brand_id');
            $table->index('step');
        });

        // Un solo default globale per step (brand_id NULL) e un solo override
        // per (brand_id, step). In Postgres i NULL sono distinti tra loro in un
        // indice unique standard, quindi serve un indice parziale per il caso
        // globale invece di affidarsi a table()->unique(['brand_id','step']).
        DB::statement(
            'CREATE UNIQUE INDEX ai_generation_settings_global_step_unique
             ON ai_generation_settings (step) WHERE brand_id IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX ai_generation_settings_brand_step_unique
             ON ai_generation_settings (brand_id, step) WHERE brand_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_settings');
    }
};
