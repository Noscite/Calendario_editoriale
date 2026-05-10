<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->string('codice_istat', 6)->primary();
            $table->string('nome', 200);
            $table->string('nome_normalized', 200)->index();   // lowercase, accenti rimossi, per match con territorial_events.city
            $table->string('provincia', 2)->index();           // sigla 'MI', 'RM', ...
            $table->string('regione', 50)->index();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
