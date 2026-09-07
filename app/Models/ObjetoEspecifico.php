<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObjetoEspecifico extends Model
{
    use HasFactory;
    protected $table = 'objeto_especifico';
    public $timestamps = false;
    protected $fillable = ['id_cuenta','codigo', 'nombre'];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta');
    }

    // Relación inversa: un objeto específico puede tener muchos materiales
    public function materiales()
    {
        return $this->hasMany(Materiales::class, 'id_objespecifico');
    }

}
