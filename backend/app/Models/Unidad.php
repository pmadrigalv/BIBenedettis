<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'nombre_unidad',
    'id_estado',
    'ciudad',
    'ip_unidad',
    'id_zona',
    'id_region',
    'uactip_unidad',
    'fapertura_unidad',
    'telefono_unidad',
    'id_tipounidad',
    'status_unidad',
    'alcancepedido_unidad',
    'clave_unidad',
])]
class Unidad extends Model
{
    use HasFactory;

    protected $table = 'unidades';

    protected $primaryKey = 'id_unidad';

    public $incrementing = true;

    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'uactip_unidad' => 'datetime',
            'fapertura_unidad' => 'date',
            'status_unidad' => 'integer',
            'alcancepedido_unidad' => 'integer',
        ];
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'unidad_usuario',
            'id_unidad',
            'id_usuario',
            'id_unidad',
            'id_usuario'
        )->withTimestamps();
    }
}
