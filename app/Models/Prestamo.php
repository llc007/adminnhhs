<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prestamo extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'articulo_inventario_id',
        'nombre_articulo',
        'marca',
        'modelo',
        'numero_serie',
        'cantidad',
        'fecha_prestamo',
        'fecha_devolucion_estimada',
        'fecha_devolucion_real',
        'estado',
        'observaciones',
        'creado_por_user_id',
        'recibido_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_prestamo' => 'date',
            'fecha_devolucion_estimada' => 'date',
            'fecha_devolucion_real' => 'date',
        ];
    }

    /**
     * Get the school associated with this loan.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the borrower (docente/funcionario).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the linked general inventory article.
     */
    public function articuloInventario(): BelongsTo
    {
        return $this->belongsTo(ArticuloInventario::class, 'articulo_inventario_id');
    }

    /**
     * Get the IT staff who registered the loan.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    /**
     * Get the IT staff who received the return.
     */
    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por_user_id');
    }

    /**
     * Check if the loan is overdue.
     */
    public function getEsVencidoAttribute(): bool
    {
        return $this->estado === 'prestado' && $this->fecha_devolucion_estimada->isPast() && ! $this->fecha_devolucion_estimada->isToday();
    }
}
