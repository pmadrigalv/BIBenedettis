<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'titulo',
        'descripcion',
        'estado',
        'prioridad',
        'usuario_id',
        'tecnico_id',
        'unidad_id',
        'fecha',
        'imagenes',
        'archivo_adjunto',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'imagenes' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id_usuario');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id', 'id_unidad');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id', 'id_usuario');
    }
}
