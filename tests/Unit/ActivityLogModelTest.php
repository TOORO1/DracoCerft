<?php
/**
 * CP-U-30 a CP-U-32 — Pruebas Unitarias: Modelo ActivityLog
 *
 * Verifica que el método estático record() persiste el log correctamente,
 * captura el usuario autenticado y no lanza excepción ante errores.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-09 (Auditoría de actividad)
 */

namespace Tests\Unit;

use Tests\DracoCertTestCase;
use App\Models\ActivityLog;

class ActivityLogModelTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-U-30  record() crea el registro en la BD
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_30_record_crea_log_en_bd(): void
    {
        $admin = $this->crearAdmin();
        $this->autenticarComo($admin);

        ActivityLog::record('test_accion', 'pruebas', 'Descripción de prueba');

        $this->assertDatabaseHas('activity_logs', [
            'accion'  => 'test_accion',
            'modulo'  => 'pruebas',
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-31  record() guarda el nombre del usuario autenticado
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_31_record_guarda_nombre_usuario_autenticado(): void
    {
        $admin = $this->crearAdmin(['Nombre_Usuario' => 'Usuario Test Log']);
        $this->autenticarComo($admin);

        ActivityLog::record('login', 'auth', 'Prueba de log con usuario');

        $this->assertDatabaseHas('activity_logs', [
            'usuario_id'     => $admin->getKey(),
            'nombre_usuario' => 'Usuario Test Log',
            'modulo'         => 'auth',
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-32  record() sin usuario autenticado usa 'Sistema'
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_32_record_sin_usuario_usa_sistema(): void
    {
        // Sin autenticar (guest)
        ActivityLog::record('accion_sistema', 'sistema', 'Log sin usuario');

        $this->assertDatabaseHas('activity_logs', [
            'nombre_usuario' => 'Sistema',
            'accion'         => 'accion_sistema',
        ]);
    }
}
