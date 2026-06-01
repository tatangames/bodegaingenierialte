<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    use HasFactory;

    protected $table = 'empleados';
    public $timestamps = false;
    protected $fillable = ['nombre'];

    /**
     * Relación: un empleado tiene muchas bitácoras
     */
    public function bitacoras()
    {
        return $this->belongsToMany(
            Bitacoras::class,
            'bitacoras_empleados',
            'id_empleado',
            'id_bitacora'
        );
    }
}
