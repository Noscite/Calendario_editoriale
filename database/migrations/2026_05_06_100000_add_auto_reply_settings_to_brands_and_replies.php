<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'auto_reply_enabled')) {
                $table->boolean('auto_reply_enabled')->default(false)->after('review_ontology');
                $table->smallInteger('auto_reply_min_rating')->default(4)->after('auto_reply_enabled');
                $table->boolean('auto_reply_only_positive_sentiment')->default(true)->after('auto_reply_min_rating');
                $table->string('auto_reply_tone', 50)->default('brand_default')->after('auto_reply_only_positive_sentiment');
                $table->boolean('auto_reply_review_mode')->default(false)->after('auto_reply_tone');
                $table->smallInteger('auto_reply_delay_minutes')->default(30)->after('auto_reply_review_mode');
                $table->index('auto_reply_enabled');
            }
        });

        Schema::table('review_replies', function (Blueprint $table) {
            if (! Schema::hasColumn('review_replies', 'was_auto_approved')) {
                $table->boolean('was_auto_approved')->default(false)->after('was_edited');
                $table->index('was_auto_approved');
            }
            if (! Schema::hasColumn('review_replies', 'notify_after_send')) {
                // True quando l'auto-reply è stato creato in modalità "immediate":
                // post-invio invia l'email di conferma. False per review_mode
                // (l'utente è già stato avvisato con la pre-invio).
                $table->boolean('notify_after_send')->default(false)->after('was_auto_approved');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'auto_reply_enabled')) {
                $table->dropIndex(['auto_reply_enabled']);
                $table->dropColumn([
                    'auto_reply_enabled',
                    'auto_reply_min_rating',
                    'auto_reply_only_positive_sentiment',
                    'auto_reply_tone',
                    'auto_reply_review_mode',
                    'auto_reply_delay_minutes',
                ]);
            }
        });

        Schema::table('review_replies', function (Blueprint $table) {
            if (Schema::hasColumn('review_replies', 'was_auto_approved')) {
                $table->dropIndex(['was_auto_approved']);
                $table->dropColumn('was_auto_approved');
            }
            if (Schema::hasColumn('review_replies', 'notify_after_send')) {
                $table->dropColumn('notify_after_send');
            }
        });
    }
};
