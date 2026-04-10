<?php
/**
 * CP-U-11 a CP-U-15 — Pruebas Unitarias: PasswordResetController
 *
 * Verifica el flujo completo de recuperación de contraseña:
 * envío de enlace, seguridad anti-enumeración, validación de token
 * (válido, expirado, inválido) y actualización exitosa.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-07 (Recuperación de contraseña)
 */

namespace Tests\Unit;

use Tests\DracoCertTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetControllerTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-U-11  Enviar enlace con email existente retorna éxito
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_11_enviar_enlace_email_existente_retorna_exito(): void
    {
        $usuario = $this->crearUsuario();

        $respuesta = $this->post('/password/send', ['correo' => $usuario->Correo]);

        // Siempre redirige con mensaje de éxito (nunca revela si el correo existe)
        $respuesta->assertRedirect()
                  ->assertSessionHas('status');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-12  Enviar enlace con email inexistente — anti-enumeración
     |           El sistema NO revela si el email está registrado
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_12_enviar_enlace_email_inexistente_no_revela_informacion(): void
    {
        $respuesta = $this->post('/password/send', [
            'correo' => 'noexiste_' . uniqid() . '@test.com',
        ]);

        // Misma respuesta que con email existente (seguridad anti-enumeración)
        $respuesta->assertRedirect()
                  ->assertSessionHas('status');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-13  Formulario de reset accesible con token válido
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_13_formulario_reset_accesible_con_token_valido(): void
    {
        $usuario   = $this->crearUsuario();
        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'correo'     => $usuario->Correo,
            'token'      => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        $respuesta = $this->get('/password/reset/' . $plainToken . '?correo=' . urlencode($usuario->Correo));

        $respuesta->assertOk();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-14  Reset con token expirado (>60 min) muestra error
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_14_reset_con_token_expirado_devuelve_error(): void
    {
        $usuario    = $this->crearUsuario();
        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'correo'     => $usuario->Correo,
            'token'      => Hash::make($plainToken),
            'created_at' => Carbon::now()->subMinutes(90), // Expirado hace 30 min
        ]);

        $respuesta = $this->post('/password/update', [
            'token'                 => $plainToken,
            'correo'                => $usuario->Correo,
            'password'              => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $respuesta->assertRedirect()
                  ->assertSessionHasErrors();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-15  Reset con token válido actualiza la contraseña
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_15_reset_con_token_valido_actualiza_contrasena(): void
    {
        $usuario    = $this->crearUsuario(['password' => Hash::make('OldPass123!')]);
        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'correo'     => $usuario->Correo,
            'token'      => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        $respuesta = $this->post('/password/update', [
            'token'                 => $plainToken,
            'correo'                => $usuario->Correo,
            'password'              => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

        $respuesta->assertRedirect(route('login'));

        // Verificar que la nueva contraseña funciona
        $usuarioActualizado = \App\Models\Usuario::find($usuario->getKey());
        $this->assertTrue(Hash::check('NewPass456!', $usuarioActualizado->password));
    }
}
