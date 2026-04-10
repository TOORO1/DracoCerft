<?php
/**
 * CP-F-01 a CP-F-05 — Pruebas Funcionales: Flujo de Autenticación
 *
 * Verifica el flujo completo de login, logout y protección de rutas:
 * redirección al dashboard tras login exitoso, cierre de sesión,
 * y bloqueo de acceso a rutas protegidas sin autenticación.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-01 (Inicio de sesión), HU-05 (Control de acceso)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\Hash;

class AuthFlowTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-01  Login exitoso redirige al dashboard
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_01_login_exitoso_redirige_al_dashboard(): void
    {
        $usuario = $this->crearUsuario(['password' => Hash::make('MiClave123!')]);

        $respuesta = $this->post('/login', [
            'email'    => $usuario->Correo,
            'password' => 'MiClave123!',
        ]);

        $respuesta->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-02  Login fallido muestra error y permanece en /login
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_02_login_fallido_muestra_error(): void
    {
        $usuario = $this->crearUsuario(['password' => Hash::make('CorrectPass!')]);

        $respuesta = $this->from('/login')->post('/login', [
            'email'    => $usuario->Correo,
            'password' => 'WrongPass!',
        ]);

        $respuesta->assertRedirect('/login')
                  ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-03  Logout cierra la sesión y redirige a /login
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_03_logout_cierra_sesion(): void
    {
        $usuario = $this->crearUsuario();

        $respuesta = $this->autenticarComo($usuario)
                          ->post('/logout');

        $respuesta->assertRedirect('/login');
        $this->assertGuest();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-04  Acceder al dashboard sin autenticación redirige a /login
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_04_dashboard_sin_autenticacion_redirige_al_login(): void
    {
        $respuesta = $this->get('/dashboard');

        $respuesta->assertRedirect('/login');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-05  Usuario autenticado puede acceder al dashboard
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_05_usuario_autenticado_accede_al_dashboard(): void
    {
        $usuario = $this->crearUsuario();

        $respuesta = $this->autenticarComo($usuario)
                          ->get('/dashboard');

        $respuesta->assertOk();
    }
}
