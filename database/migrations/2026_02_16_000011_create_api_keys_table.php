<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_keys')) return;

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('key_hash', 255)->unique();
            $table->string('key_prefix', 10);
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('organization_id');
            $table->index('key_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
