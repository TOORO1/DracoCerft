<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacitacion_resultado', function (Blueprint $table) {
            $table->id();
            $table->integer('capacitacion_id');
            $table->integer('recurso_id');
            $table->integer('usuario_id');
            $table->integer('puntaje');            // porcentaje obtenido (0-100)
            $table->integer('correctas');
            $table->integer('total');
            $table->boolean('aprobado')->default(false);
            $table->json('respuestas')->nullable(); // respuestas dadas por el usuario
            $table->timestamps();

            $table->foreign('capacitacion_id')->references('idCapacitacion')->on('capacitacion')->onDelete('cascade');
            $table->foreign('recurso_id')->references('id')->on('capacitacion_recurso')->onDelete('cascade');
            $table->foreign('usuario_id')->references('idUsuario')->on('usuario')->onDelete('cascade');

            $table->index(['capacitacion_id', 'recurso_id', 'usuario_id'], 'cap_res_usr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacitacion_resultado');
    }
};
