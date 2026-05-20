<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;
    protected $table = 'reservas';
    public $timestamps = false;

    public function proyectoDestino()
    {
        return $this->belongsTo(Tipoproyecto::class, 'id_tipoproyecto_destino');
    }

}
