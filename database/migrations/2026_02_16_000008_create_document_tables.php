<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── brand_documents ────────────────────────────────────
        if (! Schema::hasTable('brand_documents')) {
            Schema::create('brand_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();

                // File info
                $table->string('filename', 255);
                $table->string('original_filename', 255);
                $table->string('file_type', 50);
                $table->integer('file_size')->nullable();
                $table->string('file_path', 500);

                // Stato elaborazione
                $table->string('extraction_status', 50)->default('pending');
                $table->string('analysis_status', 50)->default('pending');

                // Metadata
                $table->timestampTz('uploaded_at')->useCurrent();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampTz('analyzed_at')->nullable();
                $table->text('description')->nullable();

                // AI generated
                $table->text('summary')->nullable();
                $table->json('key_topics')->nullable();

                $table->index('brand_id');
            });
        }

        // ── document_chunks ────────────────────────────────────
        if (! Schema::hasTable('document_chunks')) {
            Schema::create('document_chunks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('brand_documents')->cascadeOnDelete();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();

                // Contenuto
                $table->integer('chunk_index');
                $table->text('content');
                $table->integer('token_count')->nullable();

                // Embedding — pgvector Vector(1536)
                // Usare raw SQL per la colonna vector
                $table->string('chunk_type', 50)->nullable();
                $table->integer('page_number')->nullable();

                $table->timestampTz('created_at')->useCurrent();

                $table->index('document_id');
                $table->index('brand_id');
            });

            // Aggiungi colonna embedding con pgvector (se l'estensione è disponibile)
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE document_chunks ADD COLUMN IF NOT EXISTS embedding vector(1536)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('brand_documents');
    }
};
