<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_deontological_constraints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')
                ->constrained('brands')
                ->cascadeOnDelete();
            $table->string('constraint_slug', 64);
            $table->timestamps();

            $table->unique(['brand_id', 'constraint_slug']);
            $table->index('constraint_slug');
        });

        // Data migration: brand Valentina (test) passa a free-text sector
        // multi-valore + opt-in al vincolo deontologico psicologia.
        $valentina = DB::table('brands')->where('name', 'Valentina')->first();
        if ($valentina && $valentina->sector === 'psicologia') {
            DB::table('brands')
                ->where('id', $valentina->id)
                ->update(['sector' => 'psicologia, formazione']);

            DB::table('brand_deontological_constraints')->insert([
                'brand_id'        => $valentina->id,
                'constraint_slug' => 'psicologia',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_deontological_constraints');
    }
};
