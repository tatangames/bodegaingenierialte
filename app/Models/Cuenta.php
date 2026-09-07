<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    use HasFactory;
    protected $table = 'cuenta';
    public $timestamps = false;
    protected $fillable = ['id_rubro','codigo', 'nombre'];

    public function rubro()
    {
        return $this->belongsTo(Rubro::class, 'id_rubro');
    }

    public function objetosEspecificos()
    {
        return $this->hasMany(ObjetoEspecifico::class, 'id_cuenta');
    }
}
