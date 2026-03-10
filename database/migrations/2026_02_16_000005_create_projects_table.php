<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects')) return;

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('name', 255);
            $table->date('start_date');
            $table->date('end_date');
            $table->json('platforms')->nullable();
            $table->json('posts_per_week')->nullable();
            $table->json('themes')->nullable();
            $table->text('brief')->nullable();
            $table->text('custom_prompt')->nullable();
            $table->string('status', 20)->default('draft');

            // Nuovi campi
            $table->json('reference_urls')->nullable();
            $table->text('target_audience')->nullable();
            $table->json('objectives')->nullable();
            $table->json('content_pillars')->nullable();
            $table->json('competitors')->nullable();
            $table->json('special_dates')->nullable();
            $table->json('buyer_personas')->nullable();

            $table->timestamps();

            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
