<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_brand_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('brand_document_id')->constrained('brand_documents')->cascadeOnDelete();
            $table->string('inject_mode')->default('summary'); // 'summary' | 'full_text'
            $table->timestamps();

            $table->unique(['campaign_id', 'brand_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_brand_document');
    }
};
