<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── social_connections ──────────────────────────────────
        if (! Schema::hasTable('social_connections')) {
            Schema::create('social_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                $table->string('platform', 50);

                // Token OAuth
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->timestampTz('token_expires_at')->nullable();

                // Info account esterno
                $table->string('external_account_id', 255);
                $table->string('external_account_name', 255)->nullable();
                $table->string('external_account_url', 500)->nullable();
                $table->string('account_type', 50)->nullable();

                // Metadata
                $table->boolean('is_active')->default(true);
                $table->timestampTz('connected_at')->useCurrent();
                $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampTz('last_used_at')->nullable();

                $table->index('brand_id');
                $table->index('platform');
            });
        }

        // ── post_publications ──────────────────────────────────
        if (! Schema::hasTable('post_publications')) {
            Schema::create('post_publications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->foreignId('social_connection_id')->constrained('social_connections')->cascadeOnDelete();

                // Stato pubblicazione
                $table->string('status', 50)->default('pending');
                $table->timestampTz('scheduled_for')->nullable();
                $table->timestampTz('published_at')->nullable();

                // Riferimento esterno
                $table->string('external_post_id', 255)->nullable();
                $table->string('external_post_url', 500)->nullable();

                // Errori
                $table->text('error_message')->nullable();
                $table->integer('retry_count')->default(0);
                $table->timestampTz('last_retry_at')->nullable();

                $table->timestamps();

                $table->index('post_id');
                $table->index('social_connection_id');
                $table->index('status');
            });
        }

        // ── social_metrics ─────────────────────────────────────
        if (! Schema::hasTable('social_metrics')) {
            Schema::create('social_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_connection_id')->nullable()->constrained('social_connections')->cascadeOnDelete();
                $table->foreignId('post_publication_id')->nullable()->constrained('post_publications')->cascadeOnDelete();

                // Metriche comuni
                $table->integer('impressions')->default(0);
                $table->integer('reach')->default(0);
                $table->integer('engagement')->default(0);
                $table->integer('likes')->default(0);
                $table->integer('comments')->default(0);
                $table->integer('shares')->default(0);
                $table->integer('clicks')->default(0);
                $table->integer('saves')->default(0);

                // Metriche account
                $table->integer('followers_count')->nullable();
                $table->integer('followers_gained')->nullable();

                // Periodo
                $table->timestamp('metric_date');
                $table->string('metric_type', 20)->default('daily');

                $table->timestampTz('fetched_at')->useCurrent();
                $table->json('raw_data')->nullable();

                $table->index('social_connection_id');
                $table->index('post_publication_id');
                $table->index('metric_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_metrics');
        Schema::dropIfExists('post_publications');
        Schema::dropIfExists('social_connections');
    }
};
