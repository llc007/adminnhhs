<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntrevistaCompartida extends Model
{
    use HasFactory;

    protected $table = 'entrevista_compartida';

    protected $fillable = [
        'entrevista_id',
        'user_id',
        'granted_by_user_id',
    ];

    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class, 'entrevista_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
