<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\CapacitacionController;

// ruta pública para login
Route::post('/login', [AuthController::class, 'login']);

// rutas que aceptan sesión o token (middleware debe implementar token o session)
Route::middleware('auth.token_or_session')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });

    // Documentos
    Route::get('/documentos', [DocumentoController::class, 'index']);
    Route::post('/documentos/subir', [DocumentoController::class, 'store']);
    Route::delete('/documentos/{id}', [DocumentoController::class, 'destroy']);
    Route::get('/documentos/{id}', [DocumentoController::class, 'show']);
    Route::post('/documentos/{id}/versiones', [DocumentoController::class, 'storeVersion']);

    // Capacitaciones
    Route::get('/capacitaciones', [CapacitacionController::class, 'index']);
    Route::get('/capacitaciones/{id}', [CapacitacionController::class, 'show']);
    Route::post('/capacitaciones', [CapacitacionController::class, 'store']);
    Route::delete('/capacitaciones/{id}', [CapacitacionController::class, 'destroy']);
});
