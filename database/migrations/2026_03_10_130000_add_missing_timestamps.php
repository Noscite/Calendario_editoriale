<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'api_keys',
        'brand_documents',
        'document_chunks',
        'notifications',
        'social_connections',
        'social_metrics',
        'activity_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'updated_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->timestamp('updated_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'updated_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('updated_at');
                });
            }
        }
    }
};
