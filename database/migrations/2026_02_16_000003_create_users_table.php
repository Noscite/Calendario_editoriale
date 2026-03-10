<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) return;

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique()->index();
            $table->string('hashed_password', 255);  // Python field name
            $table->string('password', 255)->nullable(); // Laravel auth compatibility
            $table->string('full_name', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role', 20)->default('editor');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Campi profilo estesi
            $table->string('phone', 30)->nullable();
            $table->string('company', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->text('notes')->nullable();

            // Laravel standard fields
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
