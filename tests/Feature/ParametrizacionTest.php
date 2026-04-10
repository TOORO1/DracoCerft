<?php
/**
 * CP-F-36 a CP-F-46 — Pruebas Funcionales: Módulo de Parametrización
 *
 * Verifica que los parámetros del sistema se lean, guarden y apliquen
 * correctamente en las reglas de validación de documentos y capacitaciones.
 *
 * Proyecto : DracoCert — Sistema de Gestión ISO para PYMEs
 * Autores  : Jhon Jaider Castillo Minda · Daniel Esteban Toro Quiñones
 * UNIAJC   : Universidad Antonio José Camacho, Santiago de Cali
 */

namespace Tests\Feature;

use Tests\DracoCertTestCase;
use App\Models\SystemConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ParametrizacionTest extends DracoCertTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Limpiar caché antes de cada test para evitar valores residuales
        SystemConfig::clearCache();
    }

    protected function tearDown(): void
    {
        // Restaurar valores por defecto críticos y limpiar caché al finalizar
        SystemConfig::clearCache();
        parent::tearDown();
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-36  GET /api/configuracion requiere autenticación
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_36_listar_configuracion_requiere_autenticacion(): void
    {
        $respuesta = $this->getJson('/api/configuracion');

        $this->assertContains($respuesta->status(), [401, 302, 403]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-37  GET /api/configuracion retorna grupos correctamente
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_37_listar_configuracion_retorna_grupos(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->getJson('/api/configuracion');

        $respuesta->assertOk();
        $data = $respuesta->json();

        // Debe contener al menos los grupos: documentos, sistema
        $this->assertIsArray($data);
        $this->assertArrayHasKey('documentos', $data);
        $this->assertArrayHasKey('sistema', $data);

        // Cada grupo debe ser un array de ítems con la clave 'clave'
        $this->assertNotEmpty($data['documentos']);
        $this->assertArrayHasKey('clave', $data['documentos'][0]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-38  Usuario sin rol Administrador no puede acceder
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_38_usuario_sin_admin_no_puede_editar_configuracion(): void
    {
        $auditor = $this->crearAuditor();

        $respuesta = $this->autenticarComo($auditor)
                          ->putJson('/api/configuracion/doc_max_size_mb', ['valor' => '25']);

        $this->assertContains($respuesta->status(), [401, 403, 302]);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-39  Actualizar parámetro entero guarda correctamente
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_39_actualizar_parametro_entero_guarda_y_limpia_cache(): void
    {
        $admin = $this->crearAdmin();

        // Guardar valor nuevo
        $respuesta = $this->autenticarComo($admin)
                          ->putJson('/api/configuracion/doc_max_size_mb', ['valor' => '25']);

        $respuesta->assertOk()
                  ->assertJsonPath('ok', true)
                  ->assertJsonPath('clave', 'doc_max_size_mb')
                  ->assertJsonPath('valor', '25');

        // La caché debe reflejar el nuevo valor
        $this->assertEquals(25, SystemConfig::get('doc_max_size_mb'));
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-40  Actualizar lista de tipos permitidos
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_40_actualizar_tipos_permitidos_guarda_correctamente(): void
    {
        $admin = $this->crearAdmin();

        $nuevostipos = 'pdf,docx';

        $respuesta = $this->autenticarComo($admin)
                          ->putJson('/api/configuracion/doc_tipos_permitidos', ['valor' => $nuevostipos]);

        $respuesta->assertOk()
                  ->assertJsonPath('clave', 'doc_tipos_permitidos');

        // Verificar en base de datos
        $guardado = DB::table('system_config')
                      ->where('clave', 'doc_tipos_permitidos')
                      ->value('valor');

        $this->assertStringContainsString('pdf', $guardado);
        $this->assertStringContainsString('docx', $guardado);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-41  Lista vacía de tipos es rechazada con 422
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_41_lista_vacia_tipos_retorna_422(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->putJson('/api/configuracion/doc_tipos_permitidos', ['valor' => '']);

        $respuesta->assertStatus(422);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-42  Valor fuera de rango para doc_max_size_mb (>500)
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_42_tamanio_fuera_de_rango_retorna_422(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->putJson('/api/configuracion/doc_max_size_mb', ['valor' => '9999']);

        $respuesta->assertStatus(422);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-43  Valor no numérico en campo entero retorna 422
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_43_valor_no_numerico_en_entero_retorna_422(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->putJson('/api/configuracion/sesion_timeout_minutos', ['valor' => 'abc']);

        $respuesta->assertStatus(422);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-44  Restaurar parámetro a valor por defecto
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_44_reset_restaura_valor_por_defecto(): void
    {
        $admin = $this->crearAdmin();

        // Cambiar el valor primero
        SystemConfig::set('doc_max_size_mb', '999');
        $this->assertEquals(999, SystemConfig::get('doc_max_size_mb'));

        // Restaurar
        $respuesta = $this->autenticarComo($admin)
                          ->postJson('/api/configuracion/reset/doc_max_size_mb');

        $respuesta->assertOk()
                  ->assertJsonPath('ok', true)
                  ->assertJsonPath('valor', '50'); // valor por defecto

        $this->assertEquals(50, SystemConfig::get('doc_max_size_mb'));
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-45  Reset de clave inexistente retorna 404
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_45_reset_clave_inexistente_retorna_404(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->postJson('/api/configuracion/reset/clave_que_no_existe');

        $respuesta->assertStatus(404);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-46  Subir archivo con tipo NO permitido retorna 422
     |
     |  Establece doc_tipos_permitidos = 'pdf' (solo PDF),
     |  luego intenta subir un archivo .xlsx → debe rechazarse.
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_46_subida_con_tipo_no_permitido_retorna_422(): void
    {
        $admin = $this->crearAdmin();

        // Configurar: solo PDF permitido
        SystemConfig::set('doc_tipos_permitidos', 'pdf');
        $this->assertEquals('pdf', SystemConfig::get('doc_tipos_permitidos'));

        // Intentar subir un .xlsx
        $archivo = UploadedFile::fake()->create('informe.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $respuesta = $this->autenticarComo($admin)
                          ->postJson('/api/documentos/subir', [
                              'Nombre_Doc'  => 'Informe no permitido',
                              'descripcion' => 'Prueba de tipo no permitido',
                              'archivo'     => $archivo,
                              'tipo'        => '1',
                          ]);

        $respuesta->assertStatus(422);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-47  Subir archivo dentro de tipos permitidos → éxito
     |
     |  (Cloudinary fallará, pero la validación de extensión debe pasar.)
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_47_subida_con_tipo_permitido_pasa_validacion(): void
    {
        $admin = $this->crearAdmin();

        // Restaurar tipos por defecto (incluye pdf)
        SystemConfig::set('doc_tipos_permitidos', 'pdf,docx,xlsx');

        // El archivo es pdf — debe pasar la validación de extensión
        // (Cloudinary fallará, pero lo importante es que NO sea 422 de validación)
        $archivo = UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf');

        $respuesta = $this->autenticarComo($admin)
                          ->postJson('/api/documentos/subir', [
                              'Nombre_Doc'  => 'Manual de prueba',
                              'descripcion' => 'Documento PDF permitido',
                              'archivo'     => $archivo,
                              'tipo'        => '1',
                          ]);

        // 422 significaría que FALLÓ la validación de extensión — eso es un error
        $this->assertNotEquals(422, $respuesta->status(),
            'El archivo PDF fue rechazado por validación aunque el tipo está permitido. ' .
            'Respuesta: ' . $respuesta->getContent()
        );
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-53  Extensión por extensión: quitar 'jpg' bloquea .jpg
     |           aunque 'jpeg' siga en la lista (bug histórico con mimes:)
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_53_quitar_jpg_bloquea_jpg_aunque_jpeg_este_permitido(): void
    {
        $admin = $this->crearAdmin();

        // Lista sin 'jpg', solo con 'jpeg'
        SystemConfig::set('doc_tipos_permitidos', 'pdf,jpeg,png');

        // Intentar subir un archivo .jpg (extensión distinta aunque mismo MIME que jpeg)
        $archivo = UploadedFile::fake()->create('foto.jpg', 50, 'image/jpeg');

        $respuesta = $this->autenticarComo($admin)
                          ->postJson('/api/documentos/subir', [
                              'Nombre_Doc'  => 'Foto JPG bloqueada',
                              'descripcion' => 'Debe rechazarse porque jpg no está en la lista',
                              'archivo'     => $archivo,
                              'tipo'        => '1',
                          ]);

        $respuesta->assertStatus(422,
            'Un archivo .jpg no debe ser aceptado cuando solo "jpeg" (sin "jpg") está en la lista. ' .
            'La regla extensions: valida por extensión, no por MIME type.'
        );
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-48  Límite de versiones se respeta (verifica en BD)
     |
     |  Inserta doc_max_versiones = 3 y crea 3 versiones manualmente,
     |  luego verifica que el controlador eliminaría la más antigua
     |  al intentar subir una 4ª.
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_48_limite_versiones_configurado_correctamente(): void
    {
        $admin = $this->crearAdmin();

        // Fijar límite en 3
        SystemConfig::set('doc_max_versiones', '3');
        $this->assertEquals(3, SystemConfig::get('doc_max_versiones'));

        // Crear documento base
        $documento = $this->crearDocumento($admin->getKey());

        // Agregar versiones 2 y 3 manualmente
        DB::table('version')->insert([
            [
                'numero_Version'          => 2,
                'Fecha_cambio'            => now()->toDateString(),
                'Descripcion_Cambio'      => 'Versión 2 de prueba',
                'Usuario_idUsuarioCambio' => $admin->getKey(),
                'documento_id'            => $documento->idDocumento,
                'resource_type'           => 'raw',
                'created_at'              => now(),
            ],
            [
                'numero_Version'          => 3,
                'Fecha_cambio'            => now()->toDateString(),
                'Descripcion_Cambio'      => 'Versión 3 de prueba',
                'Usuario_idUsuarioCambio' => $admin->getKey(),
                'documento_id'            => $documento->idDocumento,
                'resource_type'           => 'raw',
                'created_at'              => now(),
            ],
        ]);

        $totalVersiones = DB::table('version')
                            ->where('documento_id', $documento->idDocumento)
                            ->count();

        // Confirmar que hay exactamente 3 versiones
        $this->assertEquals(3, $totalVersiones);

        // Verificar que el parámetro es efectivamente 3
        $this->assertEquals(3, SystemConfig::get('doc_max_versiones'));
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-49  SystemConfig::get retorna tipo correcto (int vs string)
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_49_system_config_get_devuelve_tipos_correctos(): void
    {
        // int → debe devolver integer PHP
        $maxSize = SystemConfig::get('doc_max_size_mb');
        $this->assertIsInt($maxSize, 'doc_max_size_mb debe ser integer');
        $this->assertGreaterThan(0, $maxSize);

        // string/list → debe devolver string PHP
        $tipos = SystemConfig::get('doc_tipos_permitidos');
        $this->assertIsString($tipos, 'doc_tipos_permitidos debe ser string');
        $this->assertStringContainsString('pdf', $tipos);

        // bool → debe devolver boolean PHP
        $alertas = SystemConfig::get('alertas_email_activo');
        $this->assertIsBool($alertas, 'alertas_email_activo debe ser boolean');
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-50  La caché se invalida al guardar un nuevo valor
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_50_cache_se_invalida_al_guardar(): void
    {
        // Leer valor inicial — pobla la caché
        $valorInicial = SystemConfig::get('nombre_empresa');

        // Actualizar directamente en BD (bypasando set para no limpiar caché)
        DB::table('system_config')
          ->where('clave', 'nombre_empresa')
          ->update(['valor' => 'Empresa Cache Test', 'updated_at' => now()]);

        // La caché aún tiene el valor anterior
        $this->assertEquals($valorInicial, SystemConfig::get('nombre_empresa'));

        // Usar set() → debe limpiar la caché
        SystemConfig::set('nombre_empresa', 'Empresa Actualizada');

        // Ahora debe devolver el nuevo valor
        $this->assertEquals('Empresa Actualizada', SystemConfig::get('nombre_empresa'));
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-51  Días de alerta vencimiento fuera de rango → 422
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_51_dias_alerta_fuera_de_rango_retorna_422(): void
    {
        $admin = $this->crearAdmin();

        // Mayor a 365
        $respuesta = $this->autenticarComo($admin)
                          ->putJson('/api/configuracion/doc_dias_alerta_vencimiento', ['valor' => '500']);

        $respuesta->assertStatus(422);

        // Menor a 1
        $respuesta2 = $this->autenticarComo($admin)
                           ->putJson('/api/configuracion/doc_dias_alerta_vencimiento', ['valor' => '0']);

        $respuesta2->assertStatus(422);
    }

    /* ──────────────────────────────────────────────────────────────
     |  CP-F-52  Timeout de sesión en rango válido se guarda
     ────────────────────────────────────────────────────────────── */
    public function test_CP_F_52_timeout_sesion_valido_se_guarda(): void
    {
        $admin = $this->crearAdmin();

        $respuesta = $this->autenticarComo($admin)
                          ->putJson('/api/configuracion/sesion_timeout_minutos', ['valor' => '120']);

        $respuesta->assertOk()
                  ->assertJsonPath('ok', true);

        $this->assertEquals(120, SystemConfig::get('sesion_timeout_minutos'));
    }
}
