<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capacitacion', function (Blueprint $table) {
            // Puntaje aprobatorio: si no se especifica, 60%
            // La columna era NOT NULL sin DEFAULT, lo que causaba error 1364
            // cuando se creaba una capacitación sin ingresar el puntaje.
            $table->integer('Puntaje')->default(60)->change();
        });
    }

    public function down(): void
    {
        Schema::table('capacitacion', function (Blueprint $table) {
            $table->integer('Puntaje')->default(null)->change();
        });
    }
};
