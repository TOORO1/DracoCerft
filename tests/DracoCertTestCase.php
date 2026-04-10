<?php
/**
 * DracoCertTestCase — Clase base con helpers reutilizables para todos los tests.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * Autores  : Jhon Jaider Castillo Minda · Daniel Esteban Toro Quiñones
 * UNIAJC     :Instituto Universidad Antonio José Camacho, Santiago de Cali
 */

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;

abstract class DracoCertTestCase extends TestCase
{
    /*
     * DatabaseTransactions envuelve cada test en una transacción y la revierte
     * al terminar la BD real nunca queda modificada por los tests.
     */
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Deshabilitar CSRF para todos los tests (no aplica en producción)
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /* ─────────────────────────────────────────────────────────────
     |  HELPERS DE FIXTURES
     ───────────────────────────────────────────────────────────── */

    /**
     * Crea un usuario de prueba en la BD y lo retorna.
     * Se revierte automáticamente al finalizar el test.
     */
    protected function crearUsuario(array $sobreescribir = []): Usuario
    {
        $datos = array_merge([
            'Cedula'          => '9999' . rand(10000, 99999),
            'Nombre_Usuario'  => 'Usuario Test',
            'Correo'          => 'test_' . uniqid() . '@dracocert.test',
            'password'        => Hash::make('Password123!'),
            'Estado_idEstado' => 1,
            'Fecha_Creacion'  => now()->toDateString(),
            'Fecha_Ultima'    => now()->toDateString(),
            'Fecha_Registro'  => now()->toDateString(),
            'google2fa_enabled' => false,
        ], $sobreescribir);

        $id = DB::table('usuario')->insertGetId($datos);
        return Usuario::find($id);
    }

    /**
     * Crea un usuario con rol Administrador y lo retorna.
     */
    protected function crearAdmin(array $sobreescribir = []): Usuario
    {
        $user = $this->crearUsuario(array_merge([
            'Nombre_Usuario' => 'Admin Test',
            'Correo'         => 'admin_' . uniqid() . '@dracocert.test',
        ], $sobreescribir));

        // Buscar o crear el rol Administrador
        $rolId = DB::table('rol')->where('Nombre_rol', 'Administrador')->value('idRol');
        if ($rolId) {
            DB::table('usuario_has_rol')->insert([
                'Usuario_idUsuario' => $user->getKey(),
                'Rol_idRol'         => $rolId,
                'Fecha_Asignacion'  => now()->toDateString(),
            ]);
        }

        return $user->fresh();
    }

    /**
     * Crea un usuario con rol Auditor.
     */
    protected function crearAuditor(array $sobreescribir = []): Usuario
    {
        $user = $this->crearUsuario(array_merge([
            'Nombre_Usuario' => 'Auditor Test',
        ], $sobreescribir));

        $rolId = DB::table('rol')->where('Nombre_rol', 'Auditor')->value('idRol');
        if ($rolId) {
            DB::table('usuario_has_rol')->insert([
                'Usuario_idUsuario' => $user->getKey(),
                'Rol_idRol'         => $rolId,
                'Fecha_Asignacion'  => now()->toDateString(),
            ]);
        }

        return $user->fresh();
    }

    /**
     * Crea una norma ISO de prueba.
     */
    protected function crearNorma(string $codigo = 'ISO 9001:2015-TEST'): object
    {
        $id = DB::table('norma')->insertGetId([
            'Codigo_norma'            => $codigo,
            'Descripcion'             => 'Norma de prueba — ' . $codigo,
            'Requisitos_idRequisitos' => null,
        ]);
        return DB::table('norma')->where('idNorma', $id)->first();
    }

    /**
     * Crea una auditoría de prueba.
     */
    protected function crearAuditoria(int $creadoPor): object
    {
        $id = DB::table('auditorias')->insertGetId([
            'titulo'      => 'Auditoría de Prueba ' . uniqid(),
            'auditor'     => 'Auditor Test',
            'fecha'       => now()->toDateString(),
            'descripcion' => 'Auditoría creada para pruebas automatizadas.',
            'created_by'  => $creadoPor,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        return DB::table('auditorias')->where('id', $id)->first();
    }

    /**
     * Crea un documento de prueba.
     * Primero inserta una versión (para romper la dependencia circular) y
     * luego inserta el documento apuntando a esa versión.
     */
    protected function crearDocumento(int $creadoPor): object
    {
        // 1. Crear versión sin documento_id (nullable)
        $versionId = DB::table('version')->insertGetId([
            'numero_Version'          => 1,
            'Fecha_cambio'            => now()->toDateString(),
            'Descripcion_Cambio'      => 'Versión inicial de prueba',
            'Usuario_idUsuarioCambio' => $creadoPor,
            'documento_id'            => null,
            'resource_type'           => 'raw',
            'created_at'              => now(),
        ]);

        // 2. Crear documento apuntando a esa versión
        $id = DB::table('documento')->insertGetId([
            'Nombre_Doc'                       => 'Documento Test ' . uniqid(),
            'Ruta'                             => 'test/documento_test.pdf',
            'Fecha_creacion'                   => now()->toDateString(),
            'Fecha_Caducidad'                  => now()->addDays(60)->toDateString(),
            'Fecha_Revision'                   => now()->addDays(30)->toDateString(),
            'Usuario_idUsuarioCreador'         => $creadoPor,
            'Tipo_Documento_idTipo_Documento'  => 1,
            'Version_idVersion'                => $versionId,
        ]);

        // 3. Actualizar versión con el documento_id real
        DB::table('version')->where('idVersion', $versionId)
            ->update(['documento_id' => $id]);

        return DB::table('documento')->where('idDocumento', $id)->first();
    }

    /**
     * Autentica un usuario para pruebas de HTTP.
     */
    protected function autenticarComo(Usuario $usuario): static
    {
        return $this->actingAs($usuario);
    }
}
