<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actualizacion extends Model
{
    protected $fillable = ['titulo', 'descripcion', 'version', 'fecha_publicacion'];
}
