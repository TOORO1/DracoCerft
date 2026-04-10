<?php
/**
 * CP-U-20 a CP-U-24 — Pruebas Unitarias: AuditoriaController
 *
 * Verifica que el % de cumplimiento ISO se calcula sobre el total de
 * cláusulas del config (no solo las evaluadas), la validación del
 * enum de estado y la estructura del resumen de evaluación.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-10 (Auditoría ISO), HU-11 (Evaluación de cláusulas)
 */

namespace Tests\Unit;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\DB;

class AuditoriaControllerTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-U-20  Listar auditorías requiere autenticación
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_20_listar_auditorias_requiere_autenticacion(): void
    {
        $respuesta = $this->getJson('/api/auditorias');

        $this->assertContains($respuesta->status(), [401, 302]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-21  Resumen evaluación calcula pct sobre total config
     |
     |  ISO 9001 tiene 27 cláusulas en el config.
     |  Si solo 5 están guardadas como 'conforme', el pct debe ser
     |  round(5/27*100) = 19%, NO 100%.
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_21_resumen_evaluacion_usa_total_config_como_denominador(): void
    {
        $admin     = $this->crearAdmin();
        $norma     = $this->crearNorma('ISO 9001:2015');
        $auditoria = $this->crearAuditoria($admin->getKey());

        // Guardar SOLO 5 cláusulas de las 27 como 'conforme'
        $clausulas = config('iso_clausulas.ISO 9001', []);
        $this->assertNotEmpty($clausulas, 'El config iso_clausulas debe tener datos para ISO 9001');

        $totalConfig = count($clausulas);
        $guardadas   = 5;

        foreach (array_slice($clausulas, 0, $guardadas) as $cl) {
            DB::table('auditoria_evaluacion')->insert([
                'auditoria_id'    => $auditoria->id,
                'norma_id'        => $norma->idNorma,
                'clausula_codigo' => $cl['codigo'],
                'clausula_titulo' => $cl['titulo'],
                'estado'          => 'conforme',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        $respuesta = $this->autenticarComo($admin)
                          ->getJson("/api/auditorias/{$auditoria->id}/evaluacion/{$norma->idNorma}");

        $respuesta->assertOk();

        $pct          = $respuesta->json('resumen.pct');
        $pctEsperado  = (int) round(($guardadas / $totalConfig) * 100);

        $this->assertEquals($pctEsperado, $pct,
            "El pct debería ser {$pctEsperado}% ({$guardadas}/{$totalConfig}), no basado solo en evaluadas.");
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-22  Save evaluación rechaza estado fuera del enum
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_22_save_evaluacion_rechaza_estado_invalido(): void
    {
        $admin     = $this->crearAdmin();
        $norma     = $this->crearNorma('ISO 27001:2022');
        $auditoria = $this->crearAuditoria($admin->getKey());

        $respuesta = $this->autenticarComo($admin)
                          ->postJson("/api/auditorias/{$auditoria->id}/evaluacion", [
                              'norma_id'        => $norma->idNorma,
                              'clausula_codigo' => '4.1',
                              'clausula_titulo' => 'Contexto',
                              'estado'          => 'estado_invalido', // Fuera del enum
                          ]);

        $respuesta->assertStatus(422);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-23  Save evaluación acepta estados válidos del enum
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_23_save_evaluacion_acepta_estados_validos(): void
    {
        $admin     = $this->crearAdmin();
        $norma     = $this->crearNorma('ISO 14001:2015');
        $auditoria = $this->crearAuditoria($admin->getKey());

        foreach (['conforme', 'no_conforme', 'observacion', 'na'] as $estado) {
            $respuesta = $this->autenticarComo($admin)
                              ->postJson("/api/auditorias/{$auditoria->id}/evaluacion", [
                                  'norma_id'        => $norma->idNorma,
                                  'clausula_codigo' => '4.' . rand(1, 9),
                                  'clausula_titulo' => 'Cláusula test',
                                  'estado'          => $estado,
                              ]);

            $respuesta->assertOk(
                "El estado '{$estado}' debería ser aceptado por la validación"
            );
        }
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-24  Compliance stats retorna estructura correcta por norma
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_24_compliance_stats_retorna_estructura_correcta(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/auditoria/compliance');

        $respuesta->assertOk()
                  ->assertJsonStructure([
                      'ok',
                      'normas' => [
                          '*' => ['id', 'codigo', 'compliance', 'fuente'],
                      ],
                      'global' => ['compliance', 'total', 'vigente'],
                  ]);
    }
}
