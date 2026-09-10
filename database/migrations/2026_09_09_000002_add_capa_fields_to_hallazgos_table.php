<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hallazgos', function (Blueprint $table) {
            $table->text('causa_raiz')->nullable()->after('prioridad');
            $table->text('plan_accion')->nullable()->after('causa_raiz');
            $table->date('fecha_limite')->nullable()->after('plan_accion');
            $table->string('responsable', 255)->nullable()->after('fecha_limite');
            $table->enum('estado_accion', ['pendiente', 'en_progreso', 'completado', 'verificado'])
                  ->default('pendiente')->after('responsable');
            $table->text('evidencias')->nullable()->after('estado_accion');
            $table->date('fecha_verificacion')->nullable()->after('evidencias');
            $table->text('resultado_verificacion')->nullable()->after('fecha_verificacion');
        });
    }

    public function down(): void
    {
        Schema::table('hallazgos', function (Blueprint $table) {
            $table->dropColumn([
                'causa_raiz', 'plan_accion', 'fecha_limite', 'responsable',
                'estado_accion', 'evidencias', 'fecha_verificacion', 'resultado_verificacion',
            ]);
        });
    }
};
