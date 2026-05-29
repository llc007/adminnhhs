<?php

namespace Database\Factories;

use App\Models\ActaEntrega;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActaEntrega>
 */
class ActaEntregaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'requerimiento_id' => null,
            'recibe_user_id' => User::factory(),
            'entrega_user_id' => User::factory(),
            'fecha_entrega' => now()->toDateString(),
            'firmado_at' => null,
        ];
    }
}
