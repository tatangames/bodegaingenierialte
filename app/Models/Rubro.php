<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rubro extends Model
{
    use HasFactory;
    protected $table = 'rubro';
    public $timestamps = false;
    protected $fillable = ['codigo', 'nombre'];

    public function cuentas()
    {
        return $this->hasMany(Cuenta::class, 'id_rubro');
    }
}
