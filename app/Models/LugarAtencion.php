<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LugarAtencion extends Model
{
    protected $table = 'lugares_atencion';

    protected $fillable = [
        'school_id',
        'nombre',
        'activo'
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
