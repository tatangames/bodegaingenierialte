<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferenciaDetalle extends Model
{
    use HasFactory;
    protected $table = 'transferencia_detalle';
    public $timestamps = false;
}
