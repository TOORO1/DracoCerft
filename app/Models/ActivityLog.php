<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'nombre_usuario',
        'accion',
        'modulo',
        'descripcion',
        'ip',
        'created_at',
    ];

    /**
     * Registra una acción en el log de actividad.
     */
    public static function record(string $accion, string $modulo, string $descripcion = ''): void
    {
        try {
            $user = Auth::user();
            static::create([
                'usuario_id'     => $user ? $user->getKey() : null,
                'nombre_usuario' => $user ? $user->Nombre_Usuario : 'Sistema',
                'accion'         => $accion,
                'modulo'         => $modulo,
                'descripcion'    => $descripcion,
                'ip'             => Request::ip(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // El log nunca debe romper el flujo principal
        }
    }
}
