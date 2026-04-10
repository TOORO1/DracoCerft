<?php
/**
 * CP-U-06 a CP-U-10 — Pruebas Unitarias: TwoFactorController
 *
 * Verifica el estado 2FA, activación con código válido/inválido y
 * desactivación con contraseña correcta/incorrecta.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-06 (Autenticación de dos factores TOTP)
 */

namespace Tests\Unit;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\Hash;

class TwoFactorControllerTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-U-06  Status devuelve false cuando 2FA está desactivado
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_06_status_devuelve_false_cuando_2fa_desactivado(): void
    {
        $usuario = $this->crearUsuario(['google2fa_enabled' => false]);

        $respuesta = $this->autenticarComo($usuario)
                          ->getJson('/api/2fa/status');

        $respuesta->assertOk()
                  ->assertJson(['enabled' => false]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-07  Status devuelve true cuando 2FA está activo
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_07_status_devuelve_true_cuando_2fa_activo(): void
    {
        $usuario = $this->crearUsuario([
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        $respuesta = $this->autenticarComo($usuario)
                          ->getJson('/api/2fa/status');

        $respuesta->assertOk()
                  ->assertJson(['enabled' => true]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-08  Activar 2FA con código inválido devuelve error
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_08_activar_2fa_con_codigo_invalido_devuelve_error(): void
    {
        $usuario = $this->crearUsuario();

        // Simular sesión con secreto temporal
        $this->autenticarComo($usuario)
             ->withSession(['2fa_temp_secret' => 'JBSWY3DPEHPK3PXP']);

        $respuesta = $this->actingAs($usuario)
                          ->withSession(['2fa_temp_secret' => 'JBSWY3DPEHPK3PXP'])
                          ->post('/2fa/enable', ['code' => '000000'], [
                              'X-CSRF-TOKEN' => csrf_token(),
                          ]);

        // Debe redirigir con errores (código incorrecto)
        $respuesta->assertRedirect()
                  ->assertSessionHasErrors('code');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-09  Desactivar 2FA con contraseña correcta
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_09_desactivar_2fa_con_password_correcto(): void
    {
        $usuario = $this->crearUsuario([
            'password'          => Hash::make('MiClave123!'),
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        $respuesta = $this->autenticarComo($usuario)
                          ->post('/2fa/disable', ['password' => 'MiClave123!']);

        $respuesta->assertRedirect(route('dashboard'));

        // Verificar que 2FA quedó desactivado en la BD
        $this->assertDatabaseHas('usuario', [
            'idUsuario'         => $usuario->getKey(),
            'google2fa_enabled' => false,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-10  Desactivar 2FA con contraseña incorrecta falla
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_10_desactivar_2fa_con_password_incorrecto_falla(): void
    {
        $usuario = $this->crearUsuario([
            'password'          => Hash::make('MiClave123!'),
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        $respuesta = $this->autenticarComo($usuario)
                          ->post('/2fa/disable', ['password' => 'WrongPass!']);

        $respuesta->assertRedirect()
                  ->assertSessionHasErrors('password');
    }
}
