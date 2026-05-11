<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uid_usuario',
    'pwd_usuario',
    'nombres_usuario',
    'apellidos_usuario',
    'telefono_usuario',
    'email_usuario',
    'id_autoridad',
    'vigencia_usuario',
])]
#[Hidden(['pwd_usuario'])]
class Usuario extends Model
{
    use HasFactory;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $incrementing = true;

    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'pwd_usuario' => 'hashed',
            'vigencia_usuario' => 'boolean',
        ];
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(
            Unidad::class,
            'unidad_usuario',
            'id_usuario',
            'id_unidad',
            'id_usuario',
            'id_unidad'
        )->withTimestamps();
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(UsuarioToken::class, 'id_usuario', 'id_usuario');
    }
}
