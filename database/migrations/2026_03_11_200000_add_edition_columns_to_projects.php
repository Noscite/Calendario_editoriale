<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_project_id')->nullable()->after('organization_id');
            $table->unsignedSmallInteger('edition_number')->nullable()->after('parent_project_id');

            $table->foreign('parent_project_id')
                  ->references('id')
                  ->on('projects')
                  ->nullOnDelete();

            $table->index('parent_project_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['parent_project_id']);
            $table->dropIndex(['parent_project_id']);
            $table->dropColumn(['parent_project_id', 'edition_number']);
        });
    }
};
