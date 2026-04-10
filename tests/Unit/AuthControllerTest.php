<?php
/**
 * CP-U-01 a CP-U-05 — Pruebas Unitarias: AuthController
 *
 * Verifica la lógica de autenticación: credenciales válidas/inválidas,
 * validación de campos y detección del flujo 2FA.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-01 (Inicio de sesión)
 */

namespace Tests\Unit;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\Hash;

class AuthControllerTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-U-01  Login con credenciales válidas
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_01_login_credenciales_validas_devuelve_200(): void
    {
        $usuario = $this->crearUsuario(['password' => Hash::make('Password123!')]);

        $respuesta = $this->postJson('/login', [
            'email'    => $usuario->Correo,
            'password' => 'Password123!',
        ]);

        $respuesta->assertStatus(200)
                  ->assertJsonStructure(['user', 'token']);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-02  Login con email inexistente devuelve 401
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_02_login_email_inexistente_devuelve_401(): void
    {
        $respuesta = $this->postJson('/login', [
            'email'    => 'noexiste_' . uniqid() . '@test.com',
            'password' => 'cualquier',
        ]);

        $respuesta->assertStatus(401)
                  ->assertJson(['error' => 'Usuario no encontrado']);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-03  Login con contraseña incorrecta devuelve 401
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_03_login_password_incorrecto_devuelve_401(): void
    {
        $usuario = $this->crearUsuario(['password' => Hash::make('CorrectPass!')]);

        $respuesta = $this->postJson('/login', [
            'email'    => $usuario->Correo,
            'password' => 'WrongPass999',
        ]);

        $respuesta->assertStatus(401)
                  ->assertJson(['error' => 'Contraseña inválida']);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-04  Login con campos vacíos devuelve 422
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_04_login_campos_vacios_devuelve_422(): void
    {
        $respuesta = $this->postJson('/login', []);

        $respuesta->assertStatus(422)
                  ->assertJsonValidationErrors(['email', 'password']);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-05  Login con 2FA activo devuelve flag 2fa_required
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_05_login_con_2fa_activo_devuelve_2fa_required(): void
    {
        $usuario = $this->crearUsuario([
            'password'          => Hash::make('Password123!'),
            'google2fa_enabled' => true,
            'google2fa_secret'  => 'JBSWY3DPEHPK3PXP',
        ]);

        $respuesta = $this->postJson('/login', [
            'email'    => $usuario->Correo,
            'password' => 'Password123!',
        ]);

        $respuesta->assertStatus(200)
                  ->assertJson(['2fa_required' => true]);
    }
}
