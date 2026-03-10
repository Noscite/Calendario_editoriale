<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations')) return;

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 100)->unique()->index();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->text('address')->nullable();

            // Subscription
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('subscription_status', 20)->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // Stripe
            $table->string('stripe_customer_id', 255)->nullable();
            $table->string('stripe_subscription_id', 255)->nullable();

            // Enterprise custom
            $table->json('custom_limits')->nullable();
            $table->text('notes')->nullable();
            $table->string('api_key_hash', 255)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
