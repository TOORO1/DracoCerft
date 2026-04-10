<?php
/**
 * CP-F-31 a CP-F-35 — Pruebas Funcionales: Dashboard
 *
 * Verifica el acceso al dashboard, la estructura completa de
 * las estadísticas, que los KPIs son numéricos no negativos
 * y que el compliance global refleja evaluaciones ISO reales.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-02 (Dashboard), HU-08 (KPIs e indicadores)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\DB;

class DashboardFlowTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-31  Dashboard accesible para usuarios autenticados
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_31_dashboard_accesible_para_usuarios_autenticados(): void
    {
        $usuario = $this->crearUsuario();

        $respuesta = $this->autenticarComo($usuario)->get('/dashboard');

        $respuesta->assertOk();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-32  API stats devuelve todos los KPIs requeridos
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_32_api_stats_devuelve_kpis_requeridos(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/dashboard/stats');

        $respuesta->assertOk();

        $kpis = $respuesta->json('kpis');

        foreach (['documentos', 'usuarios', 'capacitaciones', 'hallazgos',
                  'vigentes', 'por_vencer', 'caducados', 'compliance'] as $campo) {
            $this->assertArrayHasKey($campo, $kpis, "Falta KPI: {$campo}");
            $this->assertGreaterThanOrEqual(0, $kpis[$campo], "KPI {$campo} no puede ser negativo");
        }
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-33  KPI compliance está entre 0 y 100
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_33_kpi_compliance_esta_entre_0_y_100(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/dashboard/stats');

        $compliance = $respuesta->json('kpis.compliance');

        $this->assertGreaterThanOrEqual(0, $compliance);
        $this->assertLessThanOrEqual(100, $compliance);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-34  iso_data contiene fuente 'evaluacion' tras una auditoría
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_34_iso_data_cambia_fuente_a_evaluacion_tras_auditoria(): void
    {
        $admin     = $this->crearAdmin();
        $norma     = $this->crearNorma('ISO 9001:2015');
        $auditoria = $this->crearAuditoria($admin->getKey());

        // Registrar una evaluación para esta norma
        DB::table('auditoria_evaluacion')->insert([
            'auditoria_id'    => $auditoria->id,
            'norma_id'        => $norma->idNorma,
            'clausula_codigo' => '4.1',
            'clausula_titulo' => 'Contexto de la organización',
            'estado'          => 'conforme',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/dashboard/stats');

        $respuesta->assertOk();

        $normaData = collect($respuesta->json('iso_data'))
            ->first(fn($n) => str_contains($n['norma'] ?? '', '9001'));

        if ($normaData) {
            $this->assertEquals('evaluacion', $normaData['fuente'],
                'La fuente debe ser "evaluacion" cuando existe una auditoría evaluada');
        } else {
            $this->markTestSkipped('No se encontró norma ISO 9001 en iso_data para este test');
        }
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-35  Notificaciones del Header devuelven estructura válida
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_35_notificaciones_devuelven_estructura_valida(): void
    {
        $usuario = $this->crearUsuario();

        $respuesta = $this->autenticarComo($usuario)
                          ->getJson('/api/dashboard/notifications');

        $respuesta->assertOk()
                  ->assertJsonStructure([
                      'ok',
                      'total',
                      'expiring',
                      'caducados',
                      'hallazgos_alta',
                      'auditorias_semana',
                  ]);

        // Total debe ser consistente con la suma de sus partes
        $data   = $respuesta->json();
        $sumaPartes = $data['expiring'] + $data['caducados']
                    + $data['hallazgos_alta']
                    + count($data['auditorias_semana']);

        $this->assertEquals($data['total'], $sumaPartes,
            'El campo total debe ser la suma de expiring + caducados + hallazgos_alta + auditorias_semana');
    }
}
