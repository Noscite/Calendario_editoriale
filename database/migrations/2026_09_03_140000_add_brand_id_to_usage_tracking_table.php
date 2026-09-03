<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * usage_tracking era tracciato solo a livello organization: un'org con più
 * brand vedeva i consumi sommati insieme, senza modo di capire quale brand
 * avesse davvero generato. brand_id è nullable perché le righe storiche
 * (pre-migrazione) restano senza attribuzione brand — non riattribuibili
 * con certezza a posteriori per le org con più brand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_tracking', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('organization_id')
                ->constrained()->nullOnDelete();
            $table->index(['brand_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::table('usage_tracking', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
