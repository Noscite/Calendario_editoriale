<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('sentiment', 20)->nullable()->after('status');
            $table->string('urgency', 20)->nullable()->after('sentiment');
            $table->jsonb('topics')->nullable()->after('urgency');
            $table->boolean('is_fake_suspect')->default(false)->after('topics');
            $table->string('marketing_opportunity', 30)->nullable()->after('is_fake_suspect');
            $table->text('scoring_rationale')->nullable()->after('marketing_opportunity');
            $table->string('scored_by_model', 100)->nullable()->after('scoring_rationale');
            $table->timestampTz('scored_at')->nullable()->after('scored_by_model');

            $table->index('sentiment');
            $table->index('urgency');
            $table->index('marketing_opportunity');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->jsonb('review_ontology')->nullable()->after('review_fetch_interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['sentiment']);
            $table->dropIndex(['urgency']);
            $table->dropIndex(['marketing_opportunity']);
            $table->dropColumn([
                'sentiment',
                'urgency',
                'topics',
                'is_fake_suspect',
                'marketing_opportunity',
                'scoring_rationale',
                'scored_by_model',
                'scored_at',
            ]);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('review_ontology');
        });
    }
};
