<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntradasDetalle extends Model
{
    use HasFactory;
    protected $table = 'entradas_detalle';
    public $timestamps = false;

    protected $fillable = [
        'id_entradas',
        'id_material',
        'cantidad_inicial',
        'precio'
    ];


}
