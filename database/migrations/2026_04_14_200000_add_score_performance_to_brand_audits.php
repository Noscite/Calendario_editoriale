<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_audits', function (Blueprint $table) {
            $table->integer('score_performance')->nullable()->after('score_accessibility');
        });
    }

    public function down(): void
    {
        Schema::table('brand_audits', function (Blueprint $table) {
            $table->dropColumn('score_performance');
        });
    }
};
