<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('territorial_events', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50);                      // 'e015'
            $table->string('external_id', 100);                // ID nel feed sorgente
            $table->string('title', 500);
            $table->text('abstract')->nullable();
            $table->text('description')->nullable();
            $table->json('categories')->nullable();
            $table->string('venue_name', 500)->nullable();
            $table->string('city', 200)->nullable()->index();  // per filtering futuro
            $table->string('province', 5)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('start_at')->nullable()->index();
            $table->timestamp('end_at')->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->string('image_url_external', 2000)->nullable(); // URL S3 presigned (scade)
            $table->string('image_path', 500)->nullable();          // path locale dopo download
            $table->json('raw_payload')->nullable();                // intero JSON per debug / re-mapping
            $table->string('status', 20)->default('active');        // active|cancelled
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territorial_events');
    }
};
