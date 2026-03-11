<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nullable organization_id to projects
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
        });

        // Backfill projects.organization_id from brands
        DB::statement('
            UPDATE projects
            SET organization_id = brands.organization_id
            FROM brands
            WHERE projects.brand_id = brands.id
        ');

        // Make NOT NULL + add FK and index + soft deletes
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index('organization_id');
            if (! Schema::hasColumn('projects', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 2. Add nullable organization_id to posts
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
        });

        // Backfill posts.organization_id via projects → brands
        DB::statement('
            UPDATE posts
            SET organization_id = brands.organization_id
            FROM projects
            JOIN brands ON projects.brand_id = brands.id
            WHERE posts.project_id = projects.id
        ');

        // Make NOT NULL + add FK and index + soft deletes
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index('organization_id');
            if (! Schema::hasColumn('posts', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
            if (Schema::hasColumn('posts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
            if (Schema::hasColumn('projects', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
