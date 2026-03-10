<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_plans')) return;

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_yearly', 10, 2)->nullable();

            // Limiti
            $table->integer('max_brands')->default(1);
            $table->integer('max_users')->default(1);
            $table->integer('monthly_calendar_generations')->default(3);
            $table->integer('monthly_text_tokens')->default(50000);
            $table->integer('monthly_images')->default(20);

            // Features
            $table->boolean('has_export_excel')->default(false);
            $table->boolean('has_activity_log')->default(false);
            $table->boolean('has_advanced_roles')->default(false);
            $table->boolean('has_api_access')->default(false);
            $table->boolean('has_crm_integration')->default(false);
            $table->boolean('has_auto_publishing')->default(false);
            $table->boolean('has_analytics')->default(false);
            $table->boolean('has_ab_testing')->default(false);

            // Overage
            $table->boolean('allows_overage')->default(false);
            $table->decimal('overage_price_per_1k_tokens', 10, 4)->nullable();
            $table->decimal('overage_price_per_image', 10, 4)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
