<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BitacoraEmpleado extends Model
{
    use HasFactory;
    protected $table = 'bitacoras_empleados';
    public $timestamps = false;
    protected $fillable = ['id_bitacora', 'id_empleado'];


    /**
     * Bitácora a la que pertenece
     */
    public function bitacora()
    {
        return $this->belongsTo(Bitacoras::class, 'id_bitacora');
    }

    /**
     * Empleado asignado
     */
    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_empleado');
    }

}
