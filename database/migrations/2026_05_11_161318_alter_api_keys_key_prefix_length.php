<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ampia api_keys.key_prefix da varchar(10) a varchar(20).
 *
 * Razionale: ApiKeyController::store genera prefix di 12 caratteri
 * (substr('nsc_' . random(43), 0, 12)) ma la colonna era varchar(10),
 * causando SQLSTATE[22001] truncation error su INSERT.
 *
 * Aumentato a 20 char per dare margine futuro (formato Stripe-like).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_prefix', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_prefix', 10)->change();
        });
    }
};
