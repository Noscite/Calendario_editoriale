<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log delle modifiche manuali fatte dagli utenti su post AI-generated.
 * Ogni record cattura un singolo cambio campo (content / hashtags /
 * visual_suggestion / call_to_action) → original_value vs new_value.
 *
 * Costruisce il dataset di debugging per riconciliare "cosa l'AI ha
 * sbagliato, come l'umano ha corretto".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_edit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field_name', 50);
            $table->longText('original_value');
            $table->longText('new_value');
            $table->string('edit_reason', 200)->nullable();
            $table->timestamps();

            $table->index('post_id');
            $table->index('organization_id');
            $table->index(['post_id', 'field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_edit_logs');
    }
};
