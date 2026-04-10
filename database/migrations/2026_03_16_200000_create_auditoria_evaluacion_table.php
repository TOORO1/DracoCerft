<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_evaluacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auditoria_id');
            $table->integer('norma_id');
            $table->string('clausula_codigo', 20);    // e.g. "4.1", "6.2.1", "9.1"
            $table->string('clausula_titulo', 255);
            $table->enum('estado', ['conforme', 'no_conforme', 'observacion', 'na'])->default('na');
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->foreign('auditoria_id')
                  ->references('id')->on('auditorias')
                  ->onDelete('cascade');

            $table->unique(['auditoria_id', 'clausula_codigo'], 'uq_auditoria_clausula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_evaluacion');
    }
};
