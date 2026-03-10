<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usage_tracking')) return;

        Schema::create('usage_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');

            // Contatori
            $table->integer('calendar_generations_used')->default(0);
            $table->integer('text_tokens_used')->default(0);
            $table->integer('images_generated')->default(0);

            // Overage
            $table->integer('overage_tokens')->default(0);
            $table->integer('overage_images')->default(0);
            $table->decimal('overage_cost', 10, 2)->default(0);

            $table->timestamps();

            $table->index('organization_id');
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_tracking');
    }
};
