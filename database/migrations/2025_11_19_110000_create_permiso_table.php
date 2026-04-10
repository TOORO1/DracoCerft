<?php
// Language: php
// File: database/migrations/2025_11_19_110000_create_permiso_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermisoTable extends Migration
{
    public function up()
    {
        Schema::create('permiso', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->string('guard')->default('web');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('permiso');
    }
}
