<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel Sanctum personal_access_tokens
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // Spatie Permission tables
        if (! Schema::hasTable('permissions')) {
            $tableNames = config('permission.table_names');
            $columnNames = config('permission.column_names');
            $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
            $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
            $teams = config('permission.teams');

            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });

            Schema::create('roles', function (Blueprint $table) use ($teams) {
                $table->id();
                if ($teams) {
                    $table->unsignedBigInteger('team_id')->nullable();
                    $table->index('team_id');
                }
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                if ($teams) {
                    $table->unique(['team_id', 'name', 'guard_name']);
                } else {
                    $table->unique(['name', 'guard_name']);
                }
            });

            Schema::create('model_has_permissions', function (Blueprint $table) use ($pivotPermission, $teams) {
                $table->unsignedBigInteger($pivotPermission);
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                if ($teams) {
                    $table->unsignedBigInteger('team_id')->nullable();
                    $table->index('team_id');
                    $table->primary([$pivotPermission, 'model_id', 'model_type', 'team_id'], 'model_has_permissions_permission_model_type_primary');
                } else {
                    $table->primary([$pivotPermission, 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
                }
                $table->foreign($pivotPermission)->references('id')->on('permissions')->cascadeOnDelete();
                $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            });

            Schema::create('model_has_roles', function (Blueprint $table) use ($pivotRole, $teams) {
                $table->unsignedBigInteger($pivotRole);
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                if ($teams) {
                    $table->unsignedBigInteger('team_id')->nullable();
                    $table->index('team_id');
                    $table->primary([$pivotRole, 'model_id', 'model_type', 'team_id'], 'model_has_roles_role_model_type_primary');
                } else {
                    $table->primary([$pivotRole, 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
                }
                $table->foreign($pivotRole)->references('id')->on('roles')->cascadeOnDelete();
                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            });

            Schema::create('role_has_permissions', function (Blueprint $table) use ($pivotRole, $pivotPermission) {
                $table->unsignedBigInteger($pivotPermission);
                $table->unsignedBigInteger($pivotRole);
                $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
                $table->foreign($pivotPermission)->references('id')->on('permissions')->cascadeOnDelete();
                $table->foreign($pivotRole)->references('id')->on('roles')->cascadeOnDelete();
            });

            try {
                app('cache')
                    ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
                    ->forget(config('permission.cache.key'));
            } catch (\Exception $e) {
                // Cache may not be available during migration
            }
        }

        // Laravel Horizon jobs table
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });

            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });

            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // Cache table (fallback se Redis non disponibile)
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });

            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('personal_access_tokens');
    }
};
