<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('project_id')
                ->constrained('campaigns')->nullOnDelete();
            $table->index(['campaign_id', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
    }
};
