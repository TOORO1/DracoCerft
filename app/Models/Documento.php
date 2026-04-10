<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    protected $table = 'documento';
    protected $primaryKey = 'idDocumento';
    public $timestamps = false; // Tu tabla no tiene created_at/updated_at automáticos

    protected $fillable = [
        'Nombre_Doc',
        'Ruta', // <--- Aquí guardaremos la URL de Cloudinary
        'Fecha_creacion',
        'Fecha_Caducidad',
        'Fecha_Revision',
        'Tipo_Documento_idTipo_Documento',
        'Version_idVersion',
        'Usuario_idUsuarioCreador',
    ];
}
