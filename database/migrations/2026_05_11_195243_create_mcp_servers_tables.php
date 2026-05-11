<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MCP servers per brand: default ereditato dalle campagne del brand.
        Schema::create('brand_mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 500);
            $table->text('encrypted_api_key')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('brand_id');
        });

        // MCP servers per campagna: override / aggiunta rispetto al brand.
        Schema::create('campaign_mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 500);
            $table->text('encrypted_api_key')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            // Quando true: esclude i brand MCP, usa solo i campaign MCP.
            $table->boolean('override_brand_mcp')->default(false);
            $table->timestamps();

            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_mcp_servers');
        Schema::dropIfExists('brand_mcp_servers');
    }
};
