<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BitacoraFoto extends Model
{
    //
    use HasFactory;
    protected $table = 'bitacoras_fotos';
    public $timestamps = false;

    protected $fillable = ['id_bitacora','imagen', 'descripcion'];

    /**
     * Hace que $foto->url se incluya al convertir a JSON / array
     */
    protected $appends = ['url'];

    /**
     * Bitácora a la que pertenece la foto
     */
    public function bitacora()
    {
        return $this->belongsTo(Bitacoras::class, 'id_bitacora');
    }

    /**
     * Accesor: URL pública de la imagen
     *
     * Uso en Blade:
     *   <img src="{{ $foto->url }}" alt="Foto">
     *
     * Requiere haber ejecutado: php artisan storage:link
     *
     * Genera URLs tipo:
     *   http://tu-dominio/storage/archivos/bitacoras/2026/05/bitacora_1_xxx.jpg
     */
    public function getUrlAttribute()
    {
        if (!$this->imagen) {
            return null;
        }

        return Storage::disk('archivos')->url($this->imagen);
    }
}
