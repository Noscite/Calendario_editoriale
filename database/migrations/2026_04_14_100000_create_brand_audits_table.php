<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|running|completed|failed
            $table->string('website_url', 500)->nullable();
            $table->integer('score_overall')->nullable();       // 0-100
            $table->integer('score_neuromarketing')->nullable();
            $table->integer('score_seo_geo')->nullable();
            $table->integer('score_social_coherence')->nullable();
            $table->integer('score_gdpr')->nullable();
            $table->integer('score_accessibility')->nullable();
            $table->json('raw_data')->nullable();         // dati grezzi raccolti
            $table->json('analyzer_results')->nullable(); // risultati per categoria
            $table->text('executive_summary')->nullable(); // testo narrativo Claude
            $table->json('critical_issues')->nullable();   // array problemi critici
            $table->json('recommendations')->nullable();   // array consigli prioritizzati
            $table->string('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_audits');
    }
};
