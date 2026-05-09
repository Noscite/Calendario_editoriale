<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Il feed E015 può ritornare 'province' come sigla ('MI') OPPURE nome esteso
 * ('Milano'). Lo schema iniziale a varchar(5) era ottimistico — passiamo a 100
 * per accettare entrambi i formati senza perdere dato.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('territorial_events', function (Blueprint $table) {
            $table->string('province', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('territorial_events', function (Blueprint $table) {
            $table->string('province', 5)->nullable()->change();
        });
    }
};
