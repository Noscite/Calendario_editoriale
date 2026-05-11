<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linkage opzionale Project → Campaign.
 *
 * Quando project.campaign_id è settato, la pipeline di generazione AI dei post
 * include nel prompt la Knowledge Base derivata dagli allegati della campagna
 * (CampaignAttachment con extraction_status='completed').
 *
 * Nullable: tutti i project esistenti restano non associati a una campaign,
 * comportamento attuale invariato. L'utente può collegare un project a una
 * campaign quando vuole far entrare gli allegati come KB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('brand_id')
                ->constrained('campaigns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
    }
};
