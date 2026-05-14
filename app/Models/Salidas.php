<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Salidas extends Model
{
    use HasFactory;
    protected $table = 'salidas';
    public $timestamps = false;
    protected $fillable = ['fecha', 'descripcion', 'id_tipoproyecto'];

    public function tipoproyecto()
    {
        return $this->belongsTo(TipoProyecto::class, 'id_tipoproyecto');
    }

    public function detalle()
    {
        return $this->hasMany(SalidasDetalle::class, 'id_salida');
    }

    public function detalles()
    {
        return $this->hasMany(SalidasDetalle::class, 'id_salida', 'id');
        // Ajusta: SalidaDetalle::class  → nombre real de tu modelo de detalles
        //         'id_salida'           → FK en la tabla de detalles
        //         'id'                  → PK en la tabla salidas
    }
}
