<?php
/**
 * CP-F-06 a CP-F-10 — Pruebas Funcionales: Flujo 2FA
 *
 * Verifica el ciclo completo de autenticación de dos factores:
 * acceso a la configuración, interrupción del login cuando 2FA
 * está activo, rechazo de código inválido y desactivación.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-06 (Autenticación de dos factores TOTP)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\Hash;

class TwoFactorFlowTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-06  Página de configuración 2FA es accesible
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_06_pagina_configuracion_2fa_accesible(): void
    {
        $usuario = $this->crearUsuario();

        $respuesta = $this->autenticarComo($usuario)->get('/2fa/setup');

        $respuesta->assertOk();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-07  Página de verificación sin sesión redirige a /login
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_07_verificacion_2fa_sin_sesion_redirige_al_login(): void
    {
        $respuesta = $this->get('/2fa/verify');

        $respuesta->assertRedirect('/login');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-08  Login con 2FA activo NO completa la sesión sin código
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_08_login_con_2fa_activo_no_completa_sesion(): void
    {
        $usuario = $this->crearUsuario([
            'password'          => Hash::make('MiClave123!'),
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        $this->post('/login', [
            'email'    => $usuario->Correo,
            'password' => 'MiClave123!',
        ]);

        // La sesión NO debe tener al usuario completamente autenticado
        $this->assertGuest();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-09  Código 2FA inválido durante verificación muestra error
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_09_codigo_2fa_invalido_muestra_error(): void
    {
        $usuario = $this->crearUsuario([
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        $respuesta = $this->withSession(['2fa_user_id' => $usuario->getKey()])
                          ->post('/2fa/verify', ['code' => '000000']);

        $respuesta->assertRedirect()
                  ->assertSessionHasErrors('code');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-10  Desactivar 2FA flujo completo con contraseña correcta
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_10_desactivar_2fa_flujo_completo(): void
    {
        $usuario = $this->crearUsuario([
            'password'          => Hash::make('Pass123!'),
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        $respuesta = $this->autenticarComo($usuario)
                          ->post('/2fa/disable', ['password' => 'Pass123!']);

        $respuesta->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('usuario', [
            'idUsuario'         => $usuario->getKey(),
            'google2fa_enabled' => false,
            'google2fa_secret'  => null,
        ]);
    }
}
