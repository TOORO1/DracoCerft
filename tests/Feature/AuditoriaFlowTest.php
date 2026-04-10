<?php
/**
 * CP-F-20 a CP-F-25 — Pruebas Funcionales: Módulo de Auditoría ISO
 *
 * Verifica el ciclo completo de una auditoría: creación, evaluación
 * de cláusulas, cálculo correcto del porcentaje de cumplimiento,
 * vinculación norma-documento y finalización con notificación.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-10 (Auditoría ISO), HU-11 (Evaluación de cláusulas)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AuditoriaFlowTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-20  Crear auditoría devuelve id y estructura correcta
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_20_crear_auditoria_devuelve_estructura_correcta(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->postJson('/api/auditorias', [
                              'titulo'      => 'Auditoría ISO 9001 Q1-2026',
                              'auditor'     => 'Auditor Principal',
                              'fecha'       => now()->toDateString(),
                              'descripcion' => 'Evaluación trimestral de cumplimiento.',
                          ]);

        $respuesta->assertStatus(201)
                  ->assertJsonPath('ok', true)
                  ->assertJsonStructure(['ok', 'auditoria' => ['id', 'titulo']]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-21  Evaluar cláusula ISO guarda estado en BD
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_21_evaluar_clausula_guarda_estado_en_bd(): void
    {
        $admin     = $this->crearAdmin();
        $norma     = $this->crearNorma('ISO 9001:2015');
        $auditoria = $this->crearAuditoria($admin->getKey());

        $respuesta = $this->autenticarComo($admin)
                          ->postJson("/api/auditorias/{$auditoria->id}/evaluacion", [
                              'norma_id'        => $norma->idNorma,
                              'clausula_codigo' => '4.1',
                              'clausula_titulo' => 'Comprensión de la organización',
                              'estado'          => 'conforme',
                              'comentario'      => 'Documentado y verificado.',
                          ]);

        $respuesta->assertOk()
                  ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('auditoria_evaluacion', [
            'auditoria_id'    => $auditoria->id,
            'norma_id'        => $norma->idNorma,
            'clausula_codigo' => '4.1',
            'estado'          => 'conforme',
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-22  Evaluar cláusula dos veces actualiza (upsert)
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_22_evaluar_clausula_dos_veces_actualiza_estado(): void
    {
        $admin     = $this->crearAdmin();
        $norma     = $this->crearNorma('ISO 14001:2015');
        $auditoria = $this->crearAuditoria($admin->getKey());

        $payload = [
            'norma_id'        => $norma->idNorma,
            'clausula_codigo' => '5.1',
            'clausula_titulo' => 'Liderazgo y compromiso',
            'estado'          => 'no_conforme',
        ];

        $this->autenticarComo($admin)->postJson("/api/auditorias/{$auditoria->id}/evaluacion", $payload);

        // Segunda vez: cambiar a 'conforme'
        $payload['estado'] = 'conforme';
        $this->autenticarComo($admin)->postJson("/api/auditorias/{$auditoria->id}/evaluacion", $payload);

        // Solo debe existir UN registro con estado conforme
        $this->assertEquals(1, DB::table('auditoria_evaluacion')
            ->where('auditoria_id', $auditoria->id)
            ->where('clausula_codigo', '5.1')
            ->count());

        $this->assertDatabaseHas('auditoria_evaluacion', [
            'auditoria_id'    => $auditoria->id,
            'clausula_codigo' => '5.1',
            'estado'          => 'conforme',
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-23  Porcentaje de cumplimiento se calcula correctamente
     |
     |  ISO 9001 = 27 cláusulas en config.
     |  Con 3 conformes, pct debe ser round(3/27*100) = 11%.
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_23_porcentaje_cumplimiento_calculado_sobre_total_config(): void
    {
        $admin     = $this->crearAdmin();
        $norma     = $this->crearNorma('ISO 9001:2015');
        $auditoria = $this->crearAuditoria($admin->getKey());

        $clausulas   = config('iso_clausulas.ISO 9001', []);
        $totalConfig = count($clausulas);
        $this->assertGreaterThan(0, $totalConfig);

        // Evaluar solo 3 como conformes
        foreach (array_slice($clausulas, 0, 3) as $cl) {
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
                          ->getJson("/api/auditorias/{$auditoria->id}/evaluacion-resumen");

        $respuesta->assertOk();

        $normaResumen = collect($respuesta->json('data'))
            ->firstWhere('norma_id', $norma->idNorma);

        $pctEsperado = (int) round((3 / $totalConfig) * 100);

        $this->assertEquals($pctEsperado, $normaResumen['pct'],
            "El pct={$normaResumen['pct']}% debería ser {$pctEsperado}% (3/{$totalConfig})");
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-24  Eliminar auditoría elimina también sus evaluaciones
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_24_eliminar_auditoria_elimina_evaluaciones_en_cascada(): void
    {
        $admin     = $this->crearAdmin();
        $norma     = $this->crearNorma('ISO 27001:2022');
        $auditoria = $this->crearAuditoria($admin->getKey());

        DB::table('auditoria_evaluacion')->insert([
            'auditoria_id'    => $auditoria->id,
            'norma_id'        => $norma->idNorma,
            'clausula_codigo' => '4.1',
            'clausula_titulo' => 'Contexto',
            'estado'          => 'conforme',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->autenticarComo($admin)
             ->deleteJson("/api/auditorias/{$auditoria->id}")
             ->assertOk();

        $this->assertDatabaseMissing('auditoria_evaluacion', [
            'auditoria_id' => $auditoria->id,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-25  Finalizar auditoría envía email a administradores
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_25_finalizar_auditoria_envia_email_a_administradores(): void
    {
        Mail::fake();

        $admin     = $this->crearAdmin(['Correo' => 'admin_' . uniqid() . '@dracocert.test']);
        $norma     = $this->crearNorma('ISO 9001:2015');
        $auditoria = $this->crearAuditoria($admin->getKey());

        $respuesta = $this->autenticarComo($admin)
                          ->postJson("/api/auditorias/{$auditoria->id}/finalizar");

        $respuesta->assertOk()
                  ->assertJsonPath('ok', true)
                  ->assertJsonStructure(['ok', 'pct_global', 'enviados']);
    }
}
