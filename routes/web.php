<?php
// File: routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\CapacitacionController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\PermisosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\ConfiguracionController;

Route::get('/', fn() => redirect()->route('login'));

Route::get('/login',  fn() => view('login'))->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Recuperación de contraseña (sin auth) ────────────────────
Route::get('/password/reset',         [PasswordResetController::class, 'showForgotForm'])->name('password.forgot');
Route::post('/password/send',         [PasswordResetController::class, 'sendResetLink'])->name('password.send');
Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/password/update',       [PasswordResetController::class, 'resetPassword'])->name('password.update');

// ── 2FA: verificación durante el login (sin auth completo) ───
Route::get('/2fa/verify',  [TwoFactorController::class, 'showVerify'])->name('2fa.verify');
Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify.post');

Route::middleware(['auth'])->group(function () {

    // ── 2FA: configuración (requiere estar autenticado) ──────────
    Route::get('/2fa/setup',    [TwoFactorController::class, 'showSetup'])->name('2fa.setup');
    Route::post('/2fa/enable',  [TwoFactorController::class, 'enable'])->name('2fa.enable');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');
    Route::get('/api/2fa/status',[TwoFactorController::class, 'status'])->name('2fa.status');

    /* ── Accesible por TODOS los roles autenticados ───────────── */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/api/dashboard/notifications', [DashboardController::class, 'notifications']);

    // Activity logs
    Route::get('/api/activity-logs', [ActivityLogController::class, 'index']);

    // API para que el frontend conozca el rol del usuario autenticado
    Route::get('/api/me', fn() => response()->json([
        'ok'     => true,
        'nombre' => auth()->user()?->Nombre_Usuario,
        'rol'    => auth()->user()?->roles()->first()?->Nombre_rol ?? 'Usuario',
        'id'     => auth()->id(),
    ]));

    Route::get('/gestor_documentacion', fn() => view('Gestor_Documentacion'))->name('gestor.documentacion');
    Route::get('/Capacitaciones', fn() => view('Capacitaciones'))->name('gestor.capacitaciones');

    /* ── Auditoría: Administrador y Auditor ───────────────────── */
    Route::middleware('role:Administrador,Auditor')->group(function () {
        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    });

    /* ── Usuarios y Permisos: solo Administrador ──────────────── */
    Route::middleware('role:Administrador')->group(function () {
        Route::get('/gestor_usuarios', [UsuarioController::class, 'index'])->name('gestor.usuarios');

        // Usuarios API (escritura)
        Route::post('/api/usuarios', [UsuarioController::class, 'store']);
        Route::put('/api/usuarios/{id}', [UsuarioController::class, 'update']);
        Route::patch('/api/usuarios/{id}/rol', [UsuarioController::class, 'changeRol'])->whereNumber('id');
        Route::delete('/api/usuarios/{id}', [UsuarioController::class, 'destroy']);

        // Roles y estados (escritura)
        Route::post('/api/roles', [RoleController::class, 'store']);
        Route::post('/api/estados', [EstadoController::class, 'store']);
    });

    // Lectura de usuarios, roles y estados — todos los roles autenticados
    Route::get('/api/usuarios', [UsuarioController::class, 'indexApi']);
    Route::get('/api/roles', [RoleController::class, 'index']);
    Route::get('/api/estados', [EstadoController::class, 'index']);

    // Documentos API
    Route::get('/api/documentos/folders', [DocumentoController::class, 'folders']);
    Route::get('/api/documentos', [DocumentoController::class, 'index']);
    Route::post('/api/documentos/subir', [DocumentoController::class, 'store']);
    Route::get('/api/documentos/{id}/descargar', [DocumentoController::class, 'download'])->whereNumber('id');
    Route::get('/api/documentos/{id}/versiones/{vid}/descargar', [DocumentoController::class, 'downloadVersion'])->whereNumber(['id', 'vid']);
    Route::get('/api/documentos/{id}', [DocumentoController::class, 'show'])->whereNumber('id');
    Route::delete('/api/documentos/{id}', [DocumentoController::class, 'destroy'])->whereNumber('id');
    Route::post('/api/documentos/{id}/versiones', [DocumentoController::class, 'storeVersion'])->whereNumber('id');

    // Capacitaciones API — recursos ANTES de {id} para evitar conflicto
    Route::get('/api/capacitaciones', [CapacitacionController::class, 'index']);
    Route::post('/api/capacitaciones', [CapacitacionController::class, 'store']);
    Route::get('/api/capacitaciones/{id}/recursos', [CapacitacionController::class, 'recursos'])->whereNumber('id');
    Route::post('/api/capacitaciones/{id}/recursos', [CapacitacionController::class, 'storeRecurso'])->whereNumber('id');
    Route::get('/api/capacitaciones/{id}/recursos/{recursoId}/descargar', [CapacitacionController::class, 'downloadRecurso'])->whereNumber('id')->whereNumber('recursoId');
    Route::delete('/api/capacitaciones/{id}/recursos/{recursoId}', [CapacitacionController::class, 'destroyRecurso'])->whereNumber('id')->whereNumber('recursoId');
    Route::get('/api/capacitaciones/{id}/descargar', [CapacitacionController::class, 'download'])->whereNumber('id');
    Route::get('/api/capacitaciones/{id}', [CapacitacionController::class, 'show'])->whereNumber('id');
    Route::delete('/api/capacitaciones/{id}', [CapacitacionController::class, 'destroy'])->whereNumber('id');

    // Auditoría: normas, asignaciones, hallazgos y reportes
    Route::get('/api/normas', [AuditoriaController::class, 'listNormas'])->name('api.normas');
    Route::post('/api/normas', [AuditoriaController::class, 'storeNorma'])->name('api.normas.store');
    Route::put('/api/normas/{id}', [AuditoriaController::class, 'updateNorma'])->whereNumber('id')->name('api.normas.update');
    Route::delete('/api/normas/{id}', [AuditoriaController::class, 'destroyNorma'])->whereNumber('id')->name('api.normas.destroy');
    Route::delete('/api/documentos/{docId}/normas/{normaId}', [AuditoriaController::class, 'unassignNorma'])->whereNumber(['docId', 'normaId'])->name('api.documentos.normas.remove');
    Route::get('/api/auditoria/compliance', [AuditoriaController::class, 'complianceStats'])->name('api.auditoria.compliance');

    // Auditorías CRUD
    Route::get('/api/auditorias', [AuditoriaController::class, 'listAuditorias'])->name('api.auditorias.list');
    Route::post('/api/auditorias', [AuditoriaController::class, 'storeAuditoria'])->name('api.auditorias.store');
    Route::delete('/api/auditorias/{id}', [AuditoriaController::class, 'destroyAuditoria'])->whereNumber('id')->name('api.auditorias.destroy');

    // Vinculaciones documento-norma y auditoría-documento
    Route::post('/auditoria/norma', [AuditoriaController::class, 'storeNorma'])->name('auditoria.norma.store');
    Route::post('/auditoria/documento/{id}/assign-norma', [AuditoriaController::class, 'assignNorma'])->whereNumber('id')->name('auditoria.documento.assign_norma');
    Route::post('/auditoria/{id}/attach-doc', [AuditoriaController::class, 'attachDocumentoToAuditoria'])->whereNumber('id')->name('auditoria.attach_doc');

    // Hallazgos
    Route::get('/auditoria/hallazgos', [AuditoriaController::class, 'listHallazgos'])->name('auditoria.hallazgos.list');
    Route::post('/auditoria/hallazgos', [AuditoriaController::class, 'storeHallazgo'])->name('auditoria.hallazgos.store');
    Route::put('/auditoria/hallazgos/{id}', [AuditoriaController::class, 'updateHallazgo'])->whereNumber('id')->name('auditoria.hallazgos.update');
    Route::delete('/auditoria/hallazgos/{id}', [AuditoriaController::class, 'deleteHallazgo'])->whereNumber('id')->name('auditoria.hallazgos.delete');

    // Evaluación de cláusulas ISO por auditoría
    Route::get('/api/auditorias/{auditoriaId}/evaluacion/{normaId}', [AuditoriaController::class, 'getEvaluacion'])->whereNumber(['auditoriaId', 'normaId'])->name('api.evaluacion.get');
    Route::post('/api/auditorias/{auditoriaId}/evaluacion', [AuditoriaController::class, 'saveEvaluacion'])->whereNumber('auditoriaId')->name('api.evaluacion.save');
    Route::get('/api/auditorias/{auditoriaId}/evaluacion-resumen', [AuditoriaController::class, 'resumenEvaluacion'])->whereNumber('auditoriaId')->name('api.evaluacion.resumen');
    Route::post('/api/auditorias/{auditoriaId}/finalizar', [AuditoriaController::class, 'finalizarAuditoria'])->whereNumber('auditoriaId')->name('api.auditoria.finalizar');

    // Reporte de auditoría (datos JSON para cliente)
    Route::get('/auditoria/{id}/pdf', [AuditoriaController::class, 'generatePdf'])->whereNumber('id')->name('auditoria.pdf');

    // ─── Reportes (PDF + Excel) ─────────────────────────────────
    Route::get('/reportes/pdf/cumplimiento',  [ReporteController::class, 'pdfCumplimiento'])->name('reportes.pdf.cumplimiento');
    Route::get('/reportes/pdf/hallazgos',     [ReporteController::class, 'pdfHallazgos'])->name('reportes.pdf.hallazgos');
    Route::get('/reportes/excel/documentos',  [ReporteController::class, 'excelDocumentos'])->name('reportes.excel.documentos');
    Route::get('/reportes/excel/hallazgos',   [ReporteController::class, 'excelHallazgos'])->name('reportes.excel.hallazgos');
    Route::get('/reportes/excel/cumplimiento',[ReporteController::class, 'excelCumplimiento'])->name('reportes.excel.cumplimiento');

    /* ── Alertas email: solo Administrador ───────────────────── */
    Route::middleware('role:Administrador')->group(function () {
        Route::post('/api/alertas/enviar-vencimientos', [DashboardController::class, 'enviarAlertasVencimiento'])->name('api.alertas.vencimientos');
    });

    /* ── Parametrización: solo Administrador ─────────────────── */
    Route::middleware('role:Administrador')->group(function () {
        Route::get('/configuracion', fn() => view('Configuracion'))->name('configuracion.index');
        Route::get('/api/configuracion', [ConfiguracionController::class, 'index'])->name('api.configuracion.index');
        Route::put('/api/configuracion/{clave}', [ConfiguracionController::class, 'update'])->name('api.configuracion.update');
        Route::post('/api/configuracion/reset/{clave}', [ConfiguracionController::class, 'reset'])->name('api.configuracion.reset');
    });

    /* ── Permisos: solo Administrador ────────────────────────── */
    Route::middleware('role:Administrador')->group(function () {
        Route::get('/permisos', [PermisosController::class, 'index'])->name('permisos.index');

        Route::post('/api/permisos', [PermisosController::class, 'storePermiso'])->name('api.permisos.store');
        Route::put('/api/permisos/{id}', [PermisosController::class, 'updatePermiso'])->whereNumber('id')->name('api.permisos.update');
        Route::delete('/api/permisos/{id}', [PermisosController::class, 'deletePermiso'])->whereNumber('id')->name('api.permisos.delete');

        Route::post('/permisos/rol/{id}/asignar', [PermisosController::class, 'assignPermisoToRole'])->whereNumber('id');
        Route::post('/permisos/usuario/{id}/asignar', [PermisosController::class, 'assignPermisoToUsuario'])->whereNumber('id');
        Route::delete('/permisos/rol/{rolId}/{permisoId}', [PermisosController::class, 'removePermisoFromRole'])->whereNumber('rolId')->whereNumber('permisoId');
        Route::delete('/permisos/usuario/{usuarioId}/{permisoId}', [PermisosController::class, 'removePermisoFromUsuario'])->whereNumber('usuarioId')->whereNumber('permisoId');
    });

    // Permisos lectura — Administrador y Auditor
    Route::get('/api/permisos', [PermisosController::class, 'listPermisos'])->name('api.permisos.list');
    Route::get('/api/roles/{id}/permisos', [PermisosController::class, 'listRolePermisos'])->whereNumber('id');
    Route::get('/api/usuarios/{id}/permisos', [PermisosController::class, 'listUsuarioPermisos'])->whereNumber('id');
});
