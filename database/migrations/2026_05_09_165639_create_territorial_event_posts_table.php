<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('territorial_event_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('territorial_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 30);          // announcement | reminder | recap
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['territorial_event_id', 'post_id', 'phase'], 'tep_unique');
            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territorial_event_posts');
    }
};
