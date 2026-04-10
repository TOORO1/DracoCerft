<?php
// Language: php
// File: app/Models/Permiso.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permiso';
    protected $fillable = ['nombre', 'descripcion', 'guard'];
    public $timestamps = true;
}
