<?php

namespace Database\Factories;

use App\Models\Requerimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Requerimiento>
 */
class RequerimientoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'school_id' => DB::table('schools')->first()->id ?? 1,
            'justificacion' => $this->faker->sentence(),
            'estado' => 'pendiente_rectoria',
        ];
    }
}
