<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entradas extends Model
{
    use HasFactory;
    protected $table = 'entradas';
    public $timestamps = false;

    protected $fillable = [
        'id_tipoproyecto',
        'fecha',
        'descripcion',
        'factura',
        'es_transferencia',
        'id_tipoproyecto_transferencia',
    ];

    public function tipoproyecto()
    {
        return $this->belongsTo(\App\Models\Tipoproyecto::class, 'id_tipoproyecto');
    }

    public function tipoproyectoTransferencia()
    {
        return $this->belongsTo(\App\Models\Tipoproyecto::class, 'id_tipoproyecto_transferencia');
    }

    public function detalle()
    {
        return $this->hasMany(\App\Models\EntradasDetalle::class, 'id_entradas');
    }
}
