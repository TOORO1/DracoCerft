// File: `database/migrations/2025_11_18_000001_create_auditorias_table.php`
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditoriasTable extends Migration
{
    public function up()
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('auditor')->nullable();
            $table->date('fecha')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('reporte_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('auditoria_documento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auditoria_id');
            $table->unsignedInteger('documento_id');
            $table->timestamps();

            $table->foreign('auditoria_id')->references('id')->on('auditorias')->onDelete('cascade');
            // documento.id is int; keep simple (no FK forced)
        });
    }

    public function down()
    {
        Schema::dropIfExists('auditoria_documento');
        Schema::dropIfExists('auditorias');
    }
}
