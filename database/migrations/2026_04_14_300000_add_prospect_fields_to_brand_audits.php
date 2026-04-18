<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_audits', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->change();
            $table->string('prospect_name')->nullable()->after('brand_id');
            $table->string('prospect_sector')->nullable()->after('prospect_name');
            $table->string('share_token', 64)->nullable()->unique()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('brand_audits', function (Blueprint $table) {
            $table->dropColumn(['prospect_name', 'prospect_sector', 'share_token']);
            $table->foreignId('brand_id')->nullable(false)->change();
        });
    }
};
