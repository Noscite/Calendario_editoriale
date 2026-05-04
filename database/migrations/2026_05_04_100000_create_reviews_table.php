<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── reviews ─────────────────────────────────────────────────────
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // Tenancy + relazioni
            $table->foreignId('organization_id')
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('social_connection_id')
                ->constrained('social_connections')->cascadeOnDelete();
            $table->foreignId('brand_id')
                ->constrained('brands')->cascadeOnDelete();

            // Discriminatore — per ora 'google_business', futuro: trustpilot/yelp/...
            $table->string('platform', 50);

            // Identità review esterna (dedup)
            $table->string('external_review_id', 255);

            // Reviewer
            $table->string('reviewer_name', 255)->nullable();
            $table->string('reviewer_photo_url', 500)->nullable();

            // Contenuto
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->string('language', 10)->nullable();

            // Timestamp lato provider (per dedupe edits + ordering)
            $table->timestampTz('review_created_at');
            $table->timestampTz('review_updated_at')->nullable();

            // Quando l'abbiamo fetchata
            $table->timestampTz('fetched_at')->useCurrent();

            // Workflow status (M1: solo 'new'; M2+: scored/drafted/replied/ignored)
            $table->string('status', 50)->default('new');

            // Snapshot completo per debug + future migration di campi
            $table->json('raw_payload');

            $table->timestamps();

            // Index
            $table->index('organization_id');
            $table->index('social_connection_id');
            $table->index('brand_id');
            $table->index('platform');
            $table->index('status');

            // Dedup chiave: stessa review su stessa connection non duplicabile
            $table->unique(['platform', 'social_connection_id', 'external_review_id'], 'reviews_dedupe_unique');
        });

        // ── brands.review_fetch_interval_minutes ───────────────────────
        // Override per-brand del default scheduler (30 min). Es: hospitality 10 min.
        Schema::table('brands', function (Blueprint $table) {
            $table->integer('review_fetch_interval_minutes')->nullable()->after('style_guide');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('review_fetch_interval_minutes');
        });

        Schema::dropIfExists('reviews');
    }
};
