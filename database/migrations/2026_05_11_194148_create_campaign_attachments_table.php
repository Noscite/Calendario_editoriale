<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            $table->text('extracted_text')->nullable();
            $table->string('extraction_status', 32)->default('pending');
            $table->text('extraction_error')->nullable();
            $table->timestamp('extracted_at')->nullable();

            $table->timestamps();

            $table->index('campaign_id');
            $table->index('extraction_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_attachments');
    }
};
