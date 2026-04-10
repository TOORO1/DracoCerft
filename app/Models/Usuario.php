<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuario';
    protected $primaryKey = 'idUsuario';
    public $incrementing = true;
    protected $keyType = 'int';

    // La tabla usa columnas de fecha personalizadas, no timestamps created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'Cedula',
        'Nombre_Usuario',
        'Correo',
        'password',
        'api_token',
        'google2fa_secret',
        'google2fa_enabled',
        'Estado_idEstado',
        'Fecha_Creacion',
        'Fecha_Ultima',
        'Fecha_Registro',
    ];

    protected $hidden = [
        'password',
        'api_token',
        'google2fa_secret',
    ];

    protected $casts = [
        'Fecha_Creacion' => 'date',
        'Fecha_Ultima' => 'date',
        'Fecha_Registro' => 'date',
    ];

    /**
     * Mutator: hashea la contraseña si se establece en texto plano.
     */
    public function setPasswordAttribute($value)
    {
        if (empty($value)) {
            return;
        }

        // Si parece no estar hasheada, la hasheamos
        if (!Str::startsWith($value, ['$2y$', '$argon2'])) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    /**
     * Relación muchos a muchos con roles (tabla pivot: usuario_has_rol).
     */
    public function roles()
    {
        return $this->belongsToMany(
            Rol::class,
            'usuario_has_rol',
            'Usuario_idUsuario',
            'Rol_idRol'
        )->withPivot('Fecha_Asignacion');
    }

    /**
     * Relación belongsTo con estado.
     */
    public function estado()
    {
        return $this->belongsTo(Estado::class, 'Estado_idEstado', 'idEstado');
    }
}
