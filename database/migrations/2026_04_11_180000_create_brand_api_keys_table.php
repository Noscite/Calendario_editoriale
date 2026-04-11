<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('key_name', 100);
            $table->text('encrypted_value');
            $table->timestamps();

            $table->unique(['brand_id', 'key_name']);
            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_api_keys');
    }
};
