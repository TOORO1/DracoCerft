<?php
// Language: php
// File: database/migrations/2025_11_19_110200_create_usuario_permiso_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuarioPermisoTable extends Migration
{
    public function up()
    {
        Schema::create('usuario_permiso', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('permiso_id');
            $table->timestamps();

            // No se asume nombre PK de la tabla usuarios para evitar errores; si existe, puedes añadir FK
            $table->foreign('permiso_id')->references('id')->on('permiso')->onDelete('cascade');
            $table->unique(['usuario_id','permiso_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('usuario_permiso');
    }
}
