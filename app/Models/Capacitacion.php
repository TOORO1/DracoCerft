<?php
// php
// File: app/Models/Capacitacion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Capacitacion extends Model
{
    protected $table = 'capacitacion';
    protected $primaryKey = 'idCapacitacion';
    public $timestamps = false;
    protected $fillable = [
        'Nombre_curso',
        'Fecha_Vencimiento',
        'Fecha_Realizacion',
        'Puntaje',
        'Ruta',
        'public_id',
        'resource_type',
        'tipo_archivo',
        'original_name',
    ];
}
