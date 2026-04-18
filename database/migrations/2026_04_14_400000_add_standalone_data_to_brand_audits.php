<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_audits', function (Blueprint $table) {
            // Dati prospect/standalone quando non c'è brand associato
            // { "url": "...", "company_name": "...", "sector": "...", "notes": "...", "created_by": 1 }
            $table->json('standalone_data')->nullable()->after('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('brand_audits', function (Blueprint $table) {
            $table->dropColumn('standalone_data');
        });
    }
};
