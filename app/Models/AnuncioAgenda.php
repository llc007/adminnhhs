<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnuncioAgenda extends Model
{
    use HasFactory;

    protected $table = 'anuncios_agenda';

    protected $fillable = [
        'school_id',
        'user_id',
        'titulo',
        'cuerpo',
        'color',
        'icono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reacciones()
    {
        return $this->hasMany(AnuncioReaccion::class, 'anuncio_agenda_id');
    }
}
