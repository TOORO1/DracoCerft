<?php
/**
 * CP-U-25 a CP-U-29 — Pruebas Unitarias: Modelo Usuario
 *
 * Verifica el mutador de contraseña, campos ocultos por seguridad,
 * relación con roles y formato del campo google2fa_enabled.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * HU       : HU-03 (Gestión de usuarios)
 */

namespace Tests\Unit;

use Tests\DracoCertTestCase;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioModelTest extends DracoCertTestCase
{
    /* ──────────────────────────────────────────────────────────────
     |  CP-U-25  La contraseña se hashea automáticamente al crear
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_25_password_se_hashea_automaticamente(): void
    {
        $usuario = $this->crearUsuario();

        // Forzar contraseña en texto plano a través del mutador
        $usuario->password = 'PlainPassword123!';
        $usuario->save();

        $this->assertNotEquals('PlainPassword123!', $usuario->password);
        $this->assertTrue(Hash::check('PlainPassword123!', $usuario->password));
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-26  google2fa_secret está en la lista $hidden
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_26_google2fa_secret_esta_oculto_en_serializacion(): void
    {
        $usuario = $this->crearUsuario(['google2fa_secret' => 'SECRETSECRETSECR']);

        $array = $usuario->toArray();

        $this->assertArrayNotHasKey('google2fa_secret', $array,
            'google2fa_secret NO debe estar visible en la serialización del modelo');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-27  api_token está en la lista $hidden
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_27_api_token_esta_oculto_en_serializacion(): void
    {
        $usuario = $this->crearUsuario();
        $array   = $usuario->toArray();

        $this->assertArrayNotHasKey('api_token', $array,
            'api_token NO debe estar visible en la serialización del modelo');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-28  Relación roles() retorna BelongsToMany
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_28_relacion_roles_existe_y_es_muchos_a_muchos(): void
    {
        $usuario = $this->crearUsuario();
        $relacion = $usuario->roles();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
            $relacion
        );
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-U-29  google2fa_enabled se persiste como booleano
     ────────────────────────────────────────────────────────────── */
    public function test_CP_U_29_google2fa_enabled_se_persiste_correctamente(): void
    {
        $usuario = $this->crearUsuario(['google2fa_enabled' => false]);

        $this->assertFalse((bool) $usuario->google2fa_enabled);

        $usuario->google2fa_enabled = true;
        $usuario->save();

        $recargado = Usuario::find($usuario->getKey());
        $this->assertTrue((bool) $recargado->google2fa_enabled);
    }
}
