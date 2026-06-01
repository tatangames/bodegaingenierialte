<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bitacoras extends Model
{
    protected $table = 'bitacoras';
    public $timestamps = false;
    protected $fillable = [
        'fecha',
        'nombre',
        'descripcion',
        'ubicacion',
        'latitud',
        'longitud',
        'tiempo_utilizado'
    ];

    /**
     * Relación: una bitácora tiene muchos empleados
     */
    public function empleados()
    {
        return $this->belongsToMany(
            Empleado::class,
            'bitacoras_empleados',
            'id_bitacora',
            'id_empleado'
        );
    }

    /**
     * Relación: una bitácora tiene muchas fotos
     */
    public function fotos()
    {
        return $this->hasMany(BitacoraFoto::class, 'id_bitacora');
    }


}
