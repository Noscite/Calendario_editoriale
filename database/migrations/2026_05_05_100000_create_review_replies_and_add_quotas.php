<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('review_replies')) {
            Schema::create('review_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('review_id')->constrained()->cascadeOnDelete();

                // Stato: draft / approved / sending / sent / failed / superseded
                $table->string('status', 30)->default('draft')->index();

                // Contenuto
                $table->text('body');
                $table->text('original_body')->nullable();

                // Parametri di generazione
                $table->string('tone_used', 50)->nullable();
                $table->string('marketing_strategy', 30)->nullable();
                $table->jsonb('kb_chunks_used')->nullable();
                $table->string('generated_by_model', 100)->nullable();
                $table->integer('input_tokens')->nullable();
                $table->integer('output_tokens')->nullable();
                $table->timestampTz('generated_at')->nullable();

                // Approval
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampTz('approved_at')->nullable();
                $table->boolean('was_edited')->default(false);

                // Send
                $table->timestampTz('sent_at')->nullable();
                $table->string('external_reply_id', 500)->nullable();
                $table->text('error_message')->nullable();
                $table->integer('retry_count')->default(0);

                $table->timestamps();
                $table->index(['review_id', 'status']);
                $table->index('organization_id');
            });
        }

        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'monthly_reply_count')) {
                // null = illimitato; 0 = feature disabilitata; N = limite
                $table->integer('monthly_reply_count')->nullable()->after('monthly_calendar_generations');
            }
        });

        // La tabella usage si chiama "usage_tracking" (vedi migration 2026_02_16_000009).
        Schema::table('usage_tracking', function (Blueprint $table) {
            if (! Schema::hasColumn('usage_tracking', 'replies_sent')) {
                $table->integer('replies_sent')->default(0)->after('images_generated');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_replies');

        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'monthly_reply_count')) {
                $table->dropColumn('monthly_reply_count');
            }
        });

        Schema::table('usage_tracking', function (Blueprint $table) {
            if (Schema::hasColumn('usage_tracking', 'replies_sent')) {
                $table->dropColumn('replies_sent');
            }
        });
    }
};
