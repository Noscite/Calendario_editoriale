<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('vertical', 50)->nullable()->after('sector')->index();
            // valori: 'pro_loco', 'unpli_regional', NULL
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['vertical']);
            $table->dropColumn('vertical');
        });
    }
};
