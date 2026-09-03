<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger append-only dei crediti-post (wallet prepagato, €/post venduto a parte
 * dal piano a quota mensile). Il saldo corrente è sempre SUM(delta) per
 * organization_id — niente colonna "balance" da tenere in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_credit_ledger', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Positivo = ricarica, negativo = consumo.
            $table->integer('delta');
            $table->string('reason', 30); // purchase | consumption | adjustment

            // Valorizzato solo per reason=consumption: quale post ha consumato il credito.
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();

            // Valorizzato solo per ricariche manuali (reason=purchase|adjustment).
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference', 255)->nullable()->comment('CRO o riferimento bonifico');
            $table->text('note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_credit_ledger');
    }
};
