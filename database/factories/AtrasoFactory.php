<?php

namespace Database\Factories;

use App\Models\Atraso;
use App\Models\Estudiante;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Atraso>
 */
class AtrasoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => null,
            'estudiante_id' => Estudiante::factory(),
            'curso_id' => null,
            'registrado_por_user_id' => null,
            'fecha' => now('America/Santiago')->format('Y-m-d'),
            'hora' => '08:12:00',
            'minutos_atraso' => 12,
            'estado' => 'injustificado',
            'motivo' => null,
            'observaciones' => null,
        ];
    }
}
