<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('default_vertical', 30)->nullable()->after('subscription_status');
            $table->json('default_territory_meta')->nullable()->after('default_vertical');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['default_vertical', 'default_territory_meta']);
        });
    }
};
