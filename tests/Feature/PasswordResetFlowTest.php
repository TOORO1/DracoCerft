<?php
/**
 * CP-F-11 a CP-F-14 — Pruebas Funcionales: Recuperación de Contraseña
 *
 * Verifica el flujo completo de restablecimiento de contraseña:
 * acceso al formulario, envío del enlace, validación del token
 * y actualización exitosa con redirección al login.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-07 (Recuperación de contraseña)
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetFlowTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-F-11  Formulario ¿Olvidó su contraseña? es accesible
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_11_formulario_olvide_contrasena_accesible(): void
    {
        $respuesta = $this->get('/password/reset');

        $respuesta->assertOk();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-12  Enviar correo de recuperación con email válido
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_12_envio_correo_recuperacion_con_email_valido(): void
    {
        $usuario = $this->crearUsuario();

        $respuesta = $this->post('/password/send', ['correo' => $usuario->Correo]);

        $respuesta->assertRedirect()
                  ->assertSessionHas('status');

        // Token debe haberse creado en la tabla
        $this->assertDatabaseHas('password_reset_tokens', [
            'correo' => $usuario->Correo,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-13  Formulario de reset con token válido renderiza
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_13_formulario_reset_con_token_valido_renderiza(): void
    {
        $usuario    = $this->crearUsuario();
        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'correo'     => $usuario->Correo,
            'token'      => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        $respuesta = $this->get(
            '/password/reset/' . $plainToken . '?correo=' . urlencode($usuario->Correo)
        );

        $respuesta->assertOk();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-14  Actualizar contraseña con token válido redirige a /login
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_14_actualizar_contrasena_exitosa_redirige_al_login(): void
    {
        $usuario    = $this->crearUsuario(['password' => Hash::make('OldPass!')]);
        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'correo'     => $usuario->Correo,
            'token'      => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        $respuesta = $this->post('/password/update', [
            'token'                 => $plainToken,
            'correo'                => $usuario->Correo,
            'password'              => 'NewPass789!',
            'password_confirmation' => 'NewPass789!',
        ]);

        $respuesta->assertRedirect(route('login'))
                  ->assertSessionHas('status');

        // Token debe haber sido eliminado tras uso
        $this->assertDatabaseMissing('password_reset_tokens', [
            'correo' => $usuario->Correo,
        ]);

        // Nueva contraseña funciona
        $actualizado = \App\Models\Usuario::find($usuario->getKey());
        $this->assertTrue(Hash::check('NewPass789!', $actualizado->password));
    }
}
