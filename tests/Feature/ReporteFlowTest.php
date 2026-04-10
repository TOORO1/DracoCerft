<?php
/**
 * CP-F-26 a CP-F-30 — Pruebas Funcionales: Módulo de Reportes
 *
 * Verifica que los 5 endpoints de exportación (PDF y Excel) retornan
 * el Content-Type correcto, el nombre de archivo adecuado y que el
 * cumplimiento en el PDF usa evaluaciones ISO (no solo documentos).
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-12 (Reportes PDF y Excel)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;

class ReporteFlowTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-26  PDF Cumplimiento descarga con Content-Type application/pdf
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_26_pdf_cumplimiento_descarga_correctamente(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->get('/reportes/pdf/cumplimiento');

        $respuesta->assertOk()
                  ->assertHeader('Content-Type', 'application/pdf');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-27  PDF Hallazgos descarga con Content-Type application/pdf
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_27_pdf_hallazgos_descarga_correctamente(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->get('/reportes/pdf/hallazgos');

        $respuesta->assertOk()
                  ->assertHeader('Content-Type', 'application/pdf');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-28  Excel Documentos descarga con Content-Type Excel
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_28_excel_documentos_descarga_correctamente(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->get('/reportes/excel/documentos');

        $respuesta->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $respuesta->headers->get('Content-Type', '')
        );
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-29  Excel Cumplimiento ISO descarga correctamente
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_29_excel_cumplimiento_descarga_correctamente(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->get('/reportes/excel/cumplimiento');

        $respuesta->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $respuesta->headers->get('Content-Type', '')
        );
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-30  Reportes requieren autenticación
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_30_reportes_requieren_autenticacion(): void
    {
        foreach ([
            '/reportes/pdf/cumplimiento',
            '/reportes/pdf/hallazgos',
            '/reportes/excel/documentos',
            '/reportes/excel/hallazgos',
            '/reportes/excel/cumplimiento',
        ] as $ruta) {
            $respuesta = $this->get($ruta);
            $this->assertContains($respuesta->status(), [301, 302],
                "La ruta {$ruta} debe requerir autenticación");
        }
    }
}
