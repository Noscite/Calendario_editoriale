<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts')) return;

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('platform', 50);
            $table->date('scheduled_date')->nullable();
            $table->string('scheduled_time', 10)->nullable();
            $table->string('title', 255)->nullable();
            $table->text('content')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('pillar', 100)->nullable();
            $table->string('post_type', 50)->nullable();
            $table->text('visual_prompt')->nullable();
            $table->text('visual_suggestion')->nullable();
            $table->text('call_to_action')->nullable();
            $table->text('cta')->nullable();
            $table->text('image_prompt')->nullable();
            $table->text('image_url')->nullable();
            $table->string('media_type', 20)->default('image');
            $table->string('image_format', 20)->default('1080x1080');
            $table->json('carousel_images')->nullable();
            $table->json('carousel_prompts')->nullable();
            $table->boolean('is_carousel')->default(false);
            $table->string('content_type', 20)->default('post');
            $table->string('status', 20)->default('draft');
            $table->timestamp('created_at')->useCurrent();

            $table->string('publication_status', 50)->default('draft');

            $table->index('project_id');
            $table->index('platform');
            $table->index('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
