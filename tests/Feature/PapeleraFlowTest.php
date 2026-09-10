<?php
/**
 * CP-F-54 a CP-F-57 — Pruebas Funcionales: Papelera y Soft Delete de Documentos
 *
 * Verifica que el soft delete funcione correctamente: documentos eliminados
 * permanecen en la BD con deleted_at, no aparecen en index() ni folders(),
 * y solo los Administradores pueden ver la papelera.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-04 (Gestión de documentos ISO)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\DB;

class PapeleraFlowTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-54  Administrador puede ver documentos en la papelera
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_54_administrador_ve_documentos_en_papelera(): void
    {
        $admin    = $this->crearAdmin();
        $documento = $this->crearDocumento($admin->getKey());

        // Enviar el documento a la papelera vía el endpoint de eliminación
        $this->autenticarComo($admin)
             ->deleteJson("/api/documentos/{$documento->idDocumento}")
             ->assertOk();

        // La papelera debe devolver un array que incluye el documento eliminado
        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/documentos/papelera');

        $respuesta->assertOk();

        $ids = collect($respuesta->json())->pluck('idDocumento')->toArray();
        $this->assertContains($documento->idDocumento, $ids,
            'El documento eliminado debe aparecer en la papelera');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-55  Usuario sin rol Administrador recibe 403 al acceder
     |           a la papelera
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_55_usuario_no_admin_recibe_403_en_papelera(): void
    {
        $auditor = $this->crearAuditor();

        $respuesta = $this->autenticarComo($auditor)
                          ->getJson('/api/documentos/papelera');

        $this->assertContains($respuesta->status(), [401, 403, 302],
            'Un Auditor no debe poder acceder a la papelera');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-56  Documento con deleted_at no aparece en index()
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_56_documento_eliminado_no_aparece_en_index(): void
    {
        $admin    = $this->crearAdmin();
        $documento = $this->crearDocumento($admin->getKey());

        // Soft delete directo sobre la BD (simula el destroy())
        DB::table('documento')
            ->where('idDocumento', $documento->idDocumento)
            ->update(['deleted_at' => now()]);

        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/documentos');

        $respuesta->assertOk();

        $ids = collect($respuesta->json())->pluck('idDocumento')->toArray();
        $this->assertNotContains($documento->idDocumento, $ids,
            'Un documento en papelera no debe aparecer en el listado principal');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-57  Documento con deleted_at no se cuenta en folders()
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_57_documento_eliminado_no_se_cuenta_en_folders(): void
    {
        $admin = $this->crearAdmin();

        // Contar documentos ISO 9001 antes de insertar
        $respuestaAntes = $this->autenticarComo($admin)->getJson('/api/documentos/folders');
        $respuestaAntes->assertOk();
        $countAntes = collect($respuestaAntes->json())->firstWhere('id', 'iso9001')['count'] ?? 0;

        // Crear un documento en la carpeta iso9001 y eliminarlo
        $versionId = DB::table('version')->insertGetId([
            'numero_Version'          => 1,
            'Fecha_cambio'            => now()->toDateString(),
            'Descripcion_Cambio'      => 'Versión papelera test',
            'Usuario_idUsuarioCambio' => $admin->getKey(),
            'documento_id'            => null,
            'resource_type'           => 'raw',
            'created_at'              => now(),
        ]);
        $docId = DB::table('documento')->insertGetId([
            'Nombre_Doc'                       => 'Doc Papelera Folders ' . uniqid(),
            'Ruta'                             => 'documentos/iso9001/test.pdf',
            'Fecha_creacion'                   => now()->toDateString(),
            'Fecha_Caducidad'                  => now()->addDays(60)->toDateString(),
            'Fecha_Revision'                   => now()->addDays(30)->toDateString(),
            'Usuario_idUsuarioCreador'         => $admin->getKey(),
            'Tipo_Documento_idTipo_Documento'  => 1,
            'Version_idVersion'                => $versionId,
            'deleted_at'                       => now(), // ya eliminado
        ]);
        DB::table('version')->where('idVersion', $versionId)->update(['documento_id' => $docId]);

        // El conteo de iso9001 no debe haber aumentado
        $respuestaDespues = $this->autenticarComo($admin)->getJson('/api/documentos/folders');
        $respuestaDespues->assertOk();
        $countDespues = collect($respuestaDespues->json())->firstWhere('id', 'iso9001')['count'] ?? 0;

        $this->assertEquals($countAntes, $countDespues,
            'Un documento eliminado (deleted_at) no debe sumarse al conteo de carpetas');
    }
}
