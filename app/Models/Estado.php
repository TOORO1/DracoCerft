<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = 'estado';           // CORREGIDO: de 'estados' a 'estado'
    protected $primaryKey = 'idEstado';    // CORREGIDO: de 'id' a 'idEstado'
    public $timestamps = false;

    protected $fillable = [
        'Nombre_estado', // CORREGIDO: de 'nombre' a 'Nombre_estado'
        'Descripcion',
    ];
}
