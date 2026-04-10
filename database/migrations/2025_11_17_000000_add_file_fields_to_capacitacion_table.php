<?php
// php
// File: database/migrations/2025_11_17_000000_add_file_fields_to_capacitacion_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFileFieldsToCapacitacionTable extends Migration
{
    public function up()
    {
        Schema::table('capacitacion', function (Blueprint $table) {
            $table->string('Ruta')->nullable()->after('Nombre_curso');
            $table->string('public_id')->nullable()->after('Ruta');
            $table->string('resource_type', 20)->nullable()->after('public_id');
            $table->string('tipo_archivo', 50)->nullable()->after('resource_type');
        });
    }

    public function down()
    {
        Schema::table('capacitacion', function (Blueprint $table) {
            $table->dropColumn(['Ruta', 'public_id', 'resource_type', 'tipo_archivo']);
        });
    }
}
