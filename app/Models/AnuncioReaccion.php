<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnuncioReaccion extends Model
{
    use HasFactory;

    protected $table = 'anuncio_reacciones';

    protected $fillable = [
        'anuncio_agenda_id',
        'user_id',
        'reaction',
    ];

    public function anuncio(): BelongsTo
    {
        return $this->belongsTo(AnuncioAgenda::class, 'anuncio_agenda_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
