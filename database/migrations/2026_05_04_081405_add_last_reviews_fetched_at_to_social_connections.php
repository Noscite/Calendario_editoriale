<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('social_connections', function (Blueprint $table) {
            $table->timestampTz('last_reviews_fetched_at')->nullable()->after('last_used_at');
            $table->index('last_reviews_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('social_connections', function (Blueprint $table) {
            $table->dropIndex(['last_reviews_fetched_at']);
            $table->dropColumn('last_reviews_fetched_at');
        });
    }
};
