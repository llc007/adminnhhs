<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'bitacoras';

    protected $fillable = [
        'entrevista_id',
        'resumen',
        'observaciones',
        'acuerdos',
        'adjuntos_drive',
        'estado_formulario',
    ];

    protected $casts = [
        'acuerdos' => 'array',
        'adjuntos_drive' => 'array',
    ];

    /**
     * La entrevista original asociada
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class);
    }
}
