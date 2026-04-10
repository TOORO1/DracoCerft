<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_config', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->text('valor');
            $table->string('tipo', 20)->default('string'); // string | int | bool | list
            $table->string('grupo', 40)->default('general');
            $table->string('etiqueta', 150);
            $table->string('descripcion', 500)->nullable();
            $table->string('unidad', 30)->nullable();
            $table->timestamps();
        });

        $ahora = now();
        DB::table('system_config')->insert([
            // ── Documentos ──────────────────────────────────────────────────
            [
                'clave'       => 'doc_max_size_mb',
                'valor'       => '50',
                'tipo'        => 'int',
                'grupo'       => 'documentos',
                'etiqueta'    => 'Tamaño máximo por archivo',
                'descripcion' => 'Peso máximo permitido al subir un documento ISO. Aplica a documentos y nuevas versiones.',
                'unidad'      => 'MB',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'clave'       => 'doc_tipos_permitidos',
                'valor'       => 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,txt,csv,zip,rar',
                'tipo'        => 'list',
                'grupo'       => 'documentos',
                'etiqueta'    => 'Tipos de archivo permitidos',
                'descripcion' => 'Extensiones aceptadas al subir documentos. Separadas por coma, sin espacios ni puntos.',
                'unidad'      => null,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'clave'       => 'doc_max_versiones',
                'valor'       => '10',
                'tipo'        => 'int',
                'grupo'       => 'documentos',
                'etiqueta'    => 'Máximo de versiones por documento',
                'descripcion' => 'Al superar este límite se eliminará la versión más antigua automáticamente. Use 0 para ilimitado.',
                'unidad'      => 'versiones',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'clave'       => 'doc_dias_vigencia_default',
                'valor'       => '365',
                'tipo'        => 'int',
                'grupo'       => 'documentos',
                'etiqueta'    => 'Vigencia por defecto de documentos',
                'descripcion' => 'Días de validez asignados automáticamente al subir un documento si no se especifica fecha de caducidad.',
                'unidad'      => 'días',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'clave'       => 'doc_dias_alerta_vencimiento',
                'valor'       => '30',
                'tipo'        => 'int',
                'grupo'       => 'documentos',
                'etiqueta'    => 'Días previos para alerta de vencimiento',
                'descripcion' => 'Con cuántos días de anticipación un documento se considera "por vencer" en notificaciones y alertas email.',
                'unidad'      => 'días',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            // ── Capacitaciones ───────────────────────────────────────────────
            [
                'clave'       => 'cap_max_size_mb',
                'valor'       => '100',
                'tipo'        => 'int',
                'grupo'       => 'capacitaciones',
                'etiqueta'    => 'Tamaño máximo por archivo de capacitación',
                'descripcion' => 'Peso máximo permitido al subir archivos de capacitaciones y recursos adjuntos.',
                'unidad'      => 'MB',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'clave'       => 'cap_dias_vigencia_default',
                'valor'       => '365',
                'tipo'        => 'int',
                'grupo'       => 'capacitaciones',
                'etiqueta'    => 'Vigencia por defecto de capacitaciones',
                'descripcion' => 'Días de validez asignados automáticamente a una capacitación si no se especifica fecha de vencimiento.',
                'unidad'      => 'días',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            // ── Sistema ──────────────────────────────────────────────────────
            [
                'clave'       => 'sesion_timeout_minutos',
                'valor'       => '60',
                'tipo'        => 'int',
                'grupo'       => 'sistema',
                'etiqueta'    => 'Tiempo de inactividad de sesión',
                'descripcion' => 'Minutos de inactividad antes de que el sistema cierre la sesión automáticamente.',
                'unidad'      => 'minutos',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'clave'       => 'nombre_empresa',
                'valor'       => 'Mi Empresa',
                'tipo'        => 'string',
                'grupo'       => 'sistema',
                'etiqueta'    => 'Nombre de la empresa',
                'descripcion' => 'Nombre que aparece en los encabezados de reportes PDF y Excel generados por el sistema.',
                'unidad'      => null,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'clave'       => 'alertas_email_activo',
                'valor'       => '1',
                'tipo'        => 'bool',
                'grupo'       => 'sistema',
                'etiqueta'    => 'Alertas de vencimiento por email activas',
                'descripcion' => 'Habilita o deshabilita el envío automático diario de correos de alerta sobre documentos próximos a vencer.',
                'unidad'      => null,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            // ── Reportes ─────────────────────────────────────────────────────
            [
                'clave'       => 'reporte_logo_url',
                'valor'       => '',
                'tipo'        => 'string',
                'grupo'       => 'reportes',
                'etiqueta'    => 'URL del logo para reportes',
                'descripcion' => 'URL pública de la imagen del logo de la empresa que se imprime en los reportes PDF. Dejar vacío para usar el logo por defecto.',
                'unidad'      => null,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'clave'       => 'reporte_pie_pagina',
                'valor'       => 'Generado por DracoCert — Software de Gestión ISO',
                'tipo'        => 'string',
                'grupo'       => 'reportes',
                'etiqueta'    => 'Pie de página en reportes',
                'descripcion' => 'Texto que aparece al pie de cada página en los reportes PDF exportados.',
                'unidad'      => null,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_config');
    }
};
