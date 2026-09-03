<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: StripeWebhookController::handleSubscriptionUpdated()/handleSubscriptionDeleted()
 * referenziano stripe_price_id/slug/is_default su Plan, colonne finora mai
 * esistite — le query fallivano silenziosamente o generavano un errore SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('stripe_price_id')->nullable()->unique()->after('price_yearly');
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        DB::statement("UPDATE subscription_plans SET slug = name WHERE slug IS NULL");
        DB::table('subscription_plans')->where('name', 'small')->update(['is_default' => true]);
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['slug', 'stripe_price_id', 'is_default']);
        });
    }
};
