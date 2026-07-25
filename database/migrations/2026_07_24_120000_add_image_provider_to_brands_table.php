<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provider immagini di default per brand: 'openai' (DALL·E / gpt-image-1) o 'gemini'.
 * NULL = usa il default di sistema (services.image.default_provider).
 * Override per singola generazione via param `provider` sull'endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->string('image_provider', 20)->nullable()->after('style_guide');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->dropColumn('image_provider');
        });
    }
};
