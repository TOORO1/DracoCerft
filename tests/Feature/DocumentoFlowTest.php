<?php
/**
 * CP-F-15 a CP-F-19 — Pruebas Funcionales: Gestión Documental
 *
 * Verifica el CRUD completo de documentos: listado, subida,
 * descarga, eliminación y consulta de detalle con validaciones.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-04 (Gestión de documentos ISO)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentoFlowTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-15  Listar documentos requiere autenticación
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_15_listar_documentos_requiere_autenticacion(): void
    {
        $respuesta = $this->getJson('/api/documentos');

        $this->assertContains($respuesta->status(), [401, 302]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-16  Listar documentos devuelve estructura correcta
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_16_listar_documentos_devuelve_estructura_correcta(): void
    {
        $usuario = $this->crearAdmin();

        $respuesta = $this->autenticarComo($usuario)
                          ->getJson('/api/documentos');

        // El endpoint retorna directamente el array de documentos (JSON array)
        $respuesta->assertOk();
        $this->assertIsArray($respuesta->json());
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-17  Subir documento sin archivo devuelve error 422
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_17_subir_documento_sin_archivo_devuelve_error(): void
    {
        $usuario = $this->crearAdmin();

        $respuesta = $this->autenticarComo($usuario)
                          ->postJson('/api/documentos/subir', [
                              'nombre' => 'Doc sin archivo',
                          ]);

        $respuesta->assertStatus(422);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-18  Eliminar documento existente devuelve ok
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_18_eliminar_documento_existente_devuelve_ok(): void
    {
        $admin    = $this->crearAdmin();
        $documento = $this->crearDocumento($admin->getKey());

        $respuesta = $this->autenticarComo($admin)
                          ->deleteJson("/api/documentos/{$documento->idDocumento}");

        // El endpoint retorna {'success': true, 'message': '...'}
        $respuesta->assertOk()
                  ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('documento', [
            'idDocumento' => $documento->idDocumento,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-19  Consultar detalle de documento existente
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_19_consultar_detalle_documento_existente(): void
    {
        $admin    = $this->crearAdmin();
        $documento = $this->crearDocumento($admin->getKey());

        $respuesta = $this->autenticarComo($admin)
                          ->getJson("/api/documentos/{$documento->idDocumento}");

        // El endpoint retorna {'documento': {...}, 'versiones': [...]}
        $respuesta->assertOk()
                  ->assertJsonPath('documento.idDocumento', $documento->idDocumento);
    }
}
