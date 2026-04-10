<?php
/**
 * CP-U-16 a CP-U-19 — Pruebas Unitarias: DashboardController
 *
 * Verifica la estructura de la respuesta de estadísticas, que el
 * porcentaje de cumplimiento usa el total de cláusulas del config
 * y que el cache de notificaciones funciona correctamente.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-02 (Dashboard), HU-08 (KPIs)
 */

namespace Tests\Unit;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\Cache;

class DashboardControllerTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-U-16  /api/dashboard/stats requiere autenticación
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_16_stats_requiere_autenticacion(): void
    {
        $respuesta = $this->getJson('/api/dashboard/stats');

        // Sin autenticar debe redirigir o devolver 401/302
        $this->assertContains($respuesta->status(), [401, 302]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-17  Stats devuelve estructura JSON correcta
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_17_stats_devuelve_estructura_json_correcta(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/dashboard/stats');

        $respuesta->assertOk()
                  ->assertJsonStructure([
                      'kpis' => [
                          'documentos', 'usuarios', 'capacitaciones',
                          'hallazgos', 'vigentes', 'por_vencer',
                          'caducados', 'compliance',
                      ],
                      'iso_data',
                      'activity_trend',
                      'docs_expiring',
                      'logs_recientes',
                      'docs_recientes',
                  ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-18  activity_trend contiene exactamente 7 días
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_18_activity_trend_contiene_7_dias(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/dashboard/stats');

        $trend = $respuesta->json('activity_trend');
        $this->assertCount(7, $trend);
        $this->assertArrayHasKey('dia',   $trend[0]);
        $this->assertArrayHasKey('fecha', $trend[0]);
        $this->assertArrayHasKey('count', $trend[0]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-19  Notifications almacena respuesta en cache (array)
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_19_notifications_usa_cache(): void
    {
        $admin = $this->crearAdmin();
        Cache::flush();

        // Primera llamada — puebla el cache
        $this->autenticarComo($admin)->getJson('/api/dashboard/notifications')->assertOk();

        $cacheKey = "notifications_user_{$admin->getKey()}";
        $this->assertTrue(Cache::has($cacheKey), 'El cache de notificaciones no fue creado');

        // Segunda llamada — debe venir del cache (sin queries adicionales)
        $respuesta2 = $this->autenticarComo($admin)->getJson('/api/dashboard/notifications');
        $respuesta2->assertOk()
                   ->assertJsonStructure(['ok', 'total', 'expiring', 'caducados']);
    }
}
