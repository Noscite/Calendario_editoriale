<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('max_active_campaigns')->nullable()->after('monthly_calendar_generations')
                ->comment('Numero massimo di campagne attive (planning OR active). NULL = illimitato.');
        });

        // Backfill dei piani esistenti. Nomi attuali nel DB: small/standard/pro/unlimited/Enterprise Custom.
        // Pattern ILIKE è case-insensitive su Postgres.
        DB::table('subscription_plans')->where('name', 'ILIKE', '%small%')->update(['max_active_campaigns' => 1]);
        DB::table('subscription_plans')->where('name', 'ILIKE', '%standard%')->update(['max_active_campaigns' => 3]);
        DB::table('subscription_plans')->where('name', 'ILIKE', '%pro%')->update(['max_active_campaigns' => 10]);
        // 'unlimited' e 'Enterprise Custom' restano null (= unlimited).
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('max_active_campaigns');
        });
    }
};
