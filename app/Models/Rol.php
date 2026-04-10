<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'rol';             // CORREGIDO: de 'roles' a 'rol'
    protected $primaryKey = 'idRol';      // CORREGIDO: de 'id' a 'idRol'
    public $timestamps = false;

    protected $fillable = [
        'Nombre_rol', // CORREGIDO: de 'name' a 'Nombre_rol'
        'Descripcion',
    ];
}
