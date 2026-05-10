<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('brief')->nullable()
                ->comment('Brief della campagna: obiettivi, messaggi chiave, contesto. Usato come prompt input nella generazione (PR 3).');
            $table->string('status', 30)->default('draft')->index()
                ->comment('Stati: draft, planning, active, completed, archived. Vedi App\\Domain\\Campaign\\Enums\\CampaignStatus.');
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
