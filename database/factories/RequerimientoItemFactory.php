<?php

namespace Database\Factories;

use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequerimientoItem>
 */
class RequerimientoItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'requerimiento_id' => Requerimiento::factory(),
            'descripcion' => $this->faker->randomElement(['Notebook HP ProBook', 'Proyector Epson PowerLite', 'Escritorio Modular Oficina', 'Silla Ergonómica Ejecutiva', 'Resma de Papel Carta x500', 'Set de Tintas HP Originales']),
            'cantidad' => $this->faker->numberBetween(1, 10),
            'precio_estimado' => $this->faker->numberBetween(5000, 800000),
            'tienda_sugerida' => $this->faker->randomElement(['PC Factory', 'Sodimac', 'Mercado Libre', 'Dimeiggs', 'Prisa']),
            'estado' => 'pendiente',
        ];
    }
}
