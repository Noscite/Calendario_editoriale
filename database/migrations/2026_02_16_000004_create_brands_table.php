<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('brands')) return;

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('sector', 100)->nullable();
            $table->text('target_audience')->nullable();
            $table->string('tone_of_voice', 100)->nullable();
            $table->text('ai_profile')->nullable();
            $table->json('brand_values')->nullable();
            $table->string('website', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('facebook_url', 500)->nullable();
            $table->text('unique_selling_points')->nullable();
            $table->string('colors', 255)->nullable();
            $table->text('style_guide')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
