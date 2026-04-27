<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'is_system_tenant')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->boolean('is_system_tenant')->default(false)->after('is_active');
            });
        }

        // Noscite (id=1) è il system tenant — bypassa sempre i controlli subscription
        DB::table('organizations')->where('id', 1)->update(['is_system_tenant' => true]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('is_system_tenant');
        });
    }
};
