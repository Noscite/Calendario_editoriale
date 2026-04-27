<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->unique()
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Stato corrente della subscription
            $table->string('status', 30)->default('trial');

            // Periodo trial
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            // Periodo pagato (attivato manualmente dall'admin)
            $table->timestamp('paid_period_starts_at')->nullable();
            $table->timestamp('paid_period_ends_at')->nullable();
            $table->unsignedSmallInteger('paid_period_months')->nullable()->comment('Quanti mesi pagati: 1, 3, 6, 12');

            // Chi ha attivato e quando
            $table->foreignId('activated_by_admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('activated_at')->nullable();

            // Riferimento pagamento bonifico
            $table->string('payment_reference', 255)->nullable()->comment('CRO o riferimento bonifico');
            $table->text('payment_notes')->nullable()->comment('Note amministrative interne');

            // Contatori utilizzo durante il trial (non si resettano)
            $table->unsignedInteger('trial_tokens_consumed')->default(0);
            $table->unsignedSmallInteger('trial_calendars_generated')->default(0);

            $table->timestamps();

            // Index per query frequenti nel command schedulato
            $table->index('status');
            $table->index('trial_ends_at');
            $table->index('paid_period_ends_at');
        });

        // Backfill: crea un record subscription per ogni organization già esistente
        // Lo status viene derivato da organizations.subscription_status
        \Illuminate\Support\Facades\DB::statement("
            INSERT INTO subscriptions (organization_id, status, created_at, updated_at)
            SELECT
                id,
                CASE subscription_status
                    WHEN 'active'     THEN 'active'
                    WHEN 'trial'      THEN 'trial'
                    WHEN 'cancelled'  THEN 'cancelled'
                    WHEN 'suspended'  THEN 'expired'
                    WHEN 'past_due'   THEN 'pending_payment'
                    ELSE 'trial'
                END,
                NOW(),
                NOW()
            FROM organizations
            WHERE deleted_at IS NULL
            ON CONFLICT (organization_id) DO NOTHING
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
