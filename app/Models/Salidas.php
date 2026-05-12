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
}
