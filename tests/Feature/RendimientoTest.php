<?php
/**
 * CP-R-01 a CP-R-07 — Pruebas de Rendimiento
 *
 * Verifica que los endpoints críticos respondan dentro de los
 * umbrales de tiempo aceptables para un entorno de desarrollo
 * local con base de datos MySQL.
 *
 * Umbrales establecidos:
 *   - Endpoints con cache        : ≤ 200 ms
 *   - Endpoints simples          : ≤ 300 ms
 *   - Endpoints con múltiples JOINs : ≤ 500 ms
 *   - Descarga de reportes       : ≤ 2000 ms
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-08 (KPIs), HU-12 (Reportes)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\Cache;

class RendimientoTest extends DracoCertTestCase
{
    /** Tiempo de inicio del test actual */
    private float $startTime;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Empezar con cache limpio para medir tiempo real
    }

    /**
     * Mide el tiempo de respuesta de una petición GET autenticada.
     */
    private function medirTiempoGet(string $url, \App\Models\Usuario $usuario): float
    {
        $inicio   = microtime(true);
        $this->autenticarComo($usuario)->getJson($url);
        return (microtime(true) - $inicio) * 1000; // en milisegundos
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-R-01  /api/dashboard/stats responde en < 500 ms
     ────────────────────────────────────────────────────────────── */
    public function test_CP_R_01_dashboard_stats_responde_en_menos_de_500ms(): void
    {
        $admin  = $this->crearAdmin();
        $umbral = 500;

        $ms = $this->medirTiempoGet('/api/dashboard/stats', $admin);

        $this->assertLessThan($umbral, $ms,
            "dashboard/stats tardó {$ms}ms — umbral: {$umbral}ms");

        $this->addToAssertionCount(1);
        fwrite(STDOUT, "\n  ⏱  dashboard/stats: {$ms}ms (umbral {$umbral}ms)");
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-R-02  /api/dashboard/notifications responde en < 200 ms
     |           (primera llamada, sin cache)
     ────────────────────────────────────────────────────────────── */
    public function test_CP_R_02_notifications_primera_llamada_en_menos_de_200ms(): void
    {
        $usuario = $this->crearUsuario();
        $umbral  = 200;

        $ms = $this->medirTiempoGet('/api/dashboard/notifications', $usuario);

        $this->assertLessThan($umbral, $ms,
            "notifications (1ª llamada) tardó {$ms}ms — umbral: {$umbral}ms");

        fwrite(STDOUT, "\n  ⏱  notifications (sin cache): {$ms}ms (umbral {$umbral}ms)");
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-R-03  /api/dashboard/notifications responde en < 50 ms
     |           (segunda llamada, desde cache)
     ────────────────────────────────────────────────────────────── */
    public function test_CP_R_03_notifications_segunda_llamada_desde_cache_en_menos_de_50ms(): void
    {
        $usuario = $this->crearUsuario();
        $umbral  = 50;

        // Primera llamada — puebla el cache
        $this->autenticarComo($usuario)->getJson('/api/dashboard/notifications');

        // Segunda llamada — debe ser instantánea (desde cache)
        $ms = $this->medirTiempoGet('/api/dashboard/notifications', $usuario);

        $this->assertLessThan($umbral, $ms,
            "notifications (desde cache) tardó {$ms}ms — umbral: {$umbral}ms");

        fwrite(STDOUT, "\n  ⏱  notifications (con cache): {$ms}ms (umbral {$umbral}ms)");
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-R-04  /api/documentos responde en < 300 ms
     ────────────────────────────────────────────────────────────── */
    public function test_CP_R_04_lista_documentos_responde_en_menos_de_300ms(): void
    {
        $admin  = $this->crearAdmin();
        $umbral = 300;

        $ms = $this->medirTiempoGet('/api/documentos', $admin);

        $this->assertLessThan($umbral, $ms,
            "api/documentos tardó {$ms}ms — umbral: {$umbral}ms");

        fwrite(STDOUT, "\n  ⏱  api/documentos: {$ms}ms (umbral {$umbral}ms)");
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-R-05  /api/auditorias responde en < 300 ms
     ────────────────────────────────────────────────────────────── */
    public function test_CP_R_05_lista_auditorias_responde_en_menos_de_300ms(): void
    {
        $admin  = $this->crearAdmin();
        $umbral = 300;

        $ms = $this->medirTiempoGet('/api/auditorias', $admin);

        $this->assertLessThan($umbral, $ms,
            "api/auditorias tardó {$ms}ms — umbral: {$umbral}ms");

        fwrite(STDOUT, "\n  ⏱  api/auditorias: {$ms}ms (umbral {$umbral}ms)");
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-R-06  /api/auditoria/compliance responde en < 500 ms
     ────────────────────────────────────────────────────────────── */
    public function test_CP_R_06_compliance_stats_responde_en_menos_de_500ms(): void
    {
        $admin  = $this->crearAdmin();
        $umbral = 500;

        $ms = $this->medirTiempoGet('/api/auditoria/compliance', $admin);

        $this->assertLessThan($umbral, $ms,
            "api/auditoria/compliance tardó {$ms}ms — umbral: {$umbral}ms");

        fwrite(STDOUT, "\n  ⏱  api/auditoria/compliance: {$ms}ms (umbral {$umbral}ms)");
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-R-07  /reportes/pdf/cumplimiento genera en < 2000 ms
     ────────────────────────────────────────────────────────────── */
    public function test_CP_R_07_pdf_cumplimiento_genera_en_menos_de_2000ms(): void
    {
        $admin  = $this->crearAdmin();
        $umbral = 2000;

        $inicio   = microtime(true);
        $respuesta = $this->autenticarComo($admin)->get('/reportes/pdf/cumplimiento');
        $ms        = (microtime(true) - $inicio) * 1000;

        $respuesta->assertOk();
        $this->assertLessThan($umbral, $ms,
            "pdf/cumplimiento tardó {$ms}ms — umbral: {$umbral}ms");

        fwrite(STDOUT, "\n  ⏱  pdf/cumplimiento: {$ms}ms (umbral {$umbral}ms)");
    }
}
