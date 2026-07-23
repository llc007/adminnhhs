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
        'estado_firma',
        'firmante_nombre',
        'firmante_rut',
        'firmante_email',
        'firma_svg',
        'firmado_at',
        'firma_token',
        'firma_token_expires_at',
    ];

    protected $casts = [
        'acuerdos' => 'array',
        'adjuntos_drive' => 'array',
        'firmado_at' => 'datetime',
        'firma_token_expires_at' => 'datetime',
    ];

    /**
     * La entrevista original asociada
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class);
    }
}
