<?php

namespace Database\Factories;

use App\Models\EvaluacionDirectivo;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluacionDirectivo>
 */
class EvaluacionDirectivoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departamento = 'utp';
        $preguntas = EvaluacionDirectivo::preguntasDe($departamento);
        $respuestas = [];

        foreach (array_keys($preguntas) as $num) {
            $respuestas[(string) $num] = fake()->numberBetween(0, 3);
        }

        $totalPreguntas = count($preguntas);
        $obtenido = array_sum($respuestas);

        return [
            'school_id' => fn () => School::create(['name' => 'Colegio Test', 'domain' => fake()->unique()->domainName()])->id,
            'evaluado_id' => User::factory(),
            'departamento' => $departamento,
            'respuestas' => $respuestas,
            'puntaje_obtenido' => $obtenido,
            'puntaje_maximo' => $totalPreguntas * 3,
            'promedio' => round($obtenido / $totalPreguntas, 2),
            'registrado_por_id' => null,
        ];
    }
}
