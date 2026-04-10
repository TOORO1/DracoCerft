<?php
// php
// File: database/migrations/2025_11_18_000000_update_version_table_add_documento_fields.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateVersionTableAddDocumentoFields extends Migration
{
    public function up()
    {
        Schema::table('version', function (Blueprint $table) {
            if (!Schema::hasColumn('version', 'documento_id')) {
                $table->unsignedBigInteger('documento_id')->nullable()->after('idVersion');
                $table->string('public_id', 1000)->nullable()->after('Descripcion_Cambio');
                $table->string('nombre_archivo', 500)->nullable()->after('public_id');
                $table->timestamp('created_at')->nullable()->useCurrent()->after('Usuario_idUsuarioCambio');
                // Añadir FK si la tabla documento existe
                $table->foreign('documento_id', 'fk_version_documento')->references('idDocumento')->on('documento')->onDelete('cascade')->onUpdate('no action');
            }
        });
    }

    public function down()
    {
        Schema::table('version', function (Blueprint $table) {
            if (Schema::hasColumn('version', 'documento_id')) {
                $table->dropForeign('fk_version_documento');
                $table->dropColumn(['documento_id','public_id','nombre_archivo','created_at']);
            }
        });
    }
}
