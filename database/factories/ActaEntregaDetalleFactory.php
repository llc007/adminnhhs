<?php

namespace Database\Factories;

use App\Models\ActaEntrega;
use App\Models\ActaEntregaDetalle;
use App\Models\ArticuloInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActaEntregaDetalle>
 */
class ActaEntregaDetalleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'acta_entrega_id' => ActaEntrega::factory(),
            'articulo_inventario_id' => ArticuloInventario::factory(),
            'cantidad' => 1,
            'numero_serie' => null,
        ];
    }
}
