<?php
/**
 * CP-F-58 a CP-F-59 — Pruebas Funcionales: Campos CAPA en Hallazgos
 *
 * Verifica que los 8 campos de gestión de acción correctiva (CAPA)
 * añadidos a la tabla hallazgos se persisten correctamente y que la
 * validación del ENUM estado_accion rechaza valores no permitidos.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-10 (Auditoría ISO) — extensión modelo CAPA
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\DB;

class HallazgoCapaTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-58  Guardar hallazgo con campos CAPA completos persiste
     |           correctamente en la base de datos
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_58_guardar_hallazgo_con_campos_capa_persiste_en_bd(): void
    {
        $admin = $this->crearAdmin();

        $payload = [
            'titulo'                 => 'Hallazgo CAPA completo',
            'descripcion'            => 'No se documentan las reuniones de revisión.',
            'prioridad'              => 'alta',
            'causa_raiz'             => 'Falta de procedimiento formal de actas.',
            'plan_accion'            => 'Definir y aprobar el procedimiento P-GC-01.',
            'fecha_limite'           => now()->addDays(30)->toDateString(),
            'responsable'            => 'Responsable Calidad',
            'estado_accion'          => 'en_progreso',
            'evidencias'             => 'Borrador del procedimiento adjunto en SharePoint.',
            'fecha_verificacion'     => now()->addDays(45)->toDateString(),
            'resultado_verificacion' => 'Pendiente de revisión por dirección.',
        ];

        $respuesta = $this->autenticarComo($admin)
                          ->postJson('/auditoria/hallazgos', $payload);

        $respuesta->assertOk()
                  ->assertJsonPath('ok', true);

        $hallazgoId = $respuesta->json('hallazgo.id');
        $this->assertNotNull($hallazgoId, 'La respuesta debe incluir el id del hallazgo creado');

        // Verificar que todos los campos CAPA se persistieron
        $this->assertDatabaseHas('hallazgos', [
            'id'                     => $hallazgoId,
            'causa_raiz'             => $payload['causa_raiz'],
            'plan_accion'            => $payload['plan_accion'],
            'responsable'            => $payload['responsable'],
            'estado_accion'          => 'en_progreso',
            'resultado_verificacion' => $payload['resultado_verificacion'],
        ]);

        // Verificar los campos de fecha individualmente
        $fila = DB::table('hallazgos')->where('id', $hallazgoId)->first();
        $this->assertEquals($payload['fecha_limite'],       substr($fila->fecha_limite, 0, 10));
        $this->assertEquals($payload['fecha_verificacion'], substr($fila->fecha_verificacion, 0, 10));
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-59  estado_accion con valor fuera del ENUM es rechazado
     |           con 422 Unprocessable Entity
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_59_estado_accion_invalido_devuelve_422(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->postJson('/auditoria/hallazgos', [
                              'titulo'        => 'Hallazgo estado inválido',
                              'estado_accion' => 'aprobado', // valor fuera del ENUM
                          ]);

        $respuesta->assertStatus(422);

        // La respuesta debe indicar el campo inválido
        $errors = $respuesta->json('message');
        $this->assertNotNull($errors, 'La respuesta de error debe contener el campo message');
    }
}
