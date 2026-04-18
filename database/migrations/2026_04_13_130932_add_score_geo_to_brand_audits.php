<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('brand_audits', function (Blueprint $table) {
            $table->unsignedTinyInteger('score_geo')->nullable()->after('score_seo_geo');
        });
    }

    public function down(): void
    {
        Schema::table('brand_audits', function (Blueprint $table) {
            $table->dropColumn('score_geo');
        });
    }
};
