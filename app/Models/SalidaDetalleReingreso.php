<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalidaDetalleReingreso extends Model
{
    protected $table = 'salidas_detalle_reingreso';

    protected $fillable = [
        'id_salida_detalle',
        'cantidad',
        'fecha',
        'justificacion',
        'archivo',
    ];
}
