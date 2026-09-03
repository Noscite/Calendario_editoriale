<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log append-only di OGNI chiamata AI (Anthropic e non), indipendente dal
 * fatto che produca o meno un Post. Sostituisce/completa il tracking
 * precedente basato solo su Post::generation_metadata->usage, che non
 * copriva personas/strategy/batch falliti a 0 post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 50);
            $table->string('provider', 30)->default('anthropic');
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_creation_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->decimal('cost_eur', 12, 6)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['brand_id', 'created_at']);
            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
    }
};
