<?php

namespace Database\Factories;

use App\Models\EvaluacionDocente;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluacionDocente>
 */
class EvaluacionDocenteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $respuestas = [];
        for ($i = 1; $i <= 10; $i++) {
            $respuestas[(string) $i] = fake()->numberBetween(0, 3);
        }

        $obtenido = array_sum($respuestas);

        return [
            'school_id' => fn () => School::create(['name' => 'Colegio Test', 'domain' => fake()->unique()->domainName()])->id,
            'profesor_id' => User::factory(),
            'tipo_respondente' => 'estudiante',
            'con_jefatura' => false,
            'respuestas' => $respuestas,
            'puntaje_obtenido' => $obtenido,
            'puntaje_maximo' => 30,
            'promedio' => round($obtenido / 10, 2),
            'registrado_por_id' => null,
        ];
    }

    public function apoderado(bool $conJefatura = false): static
    {
        return $this->state(function () use ($conJefatura) {
            $totalPreguntas = $conJefatura ? 7 : 6;
            $respuestas = [];
            for ($i = 1; $i <= $totalPreguntas; $i++) {
                $respuestas[(string) $i] = fake()->numberBetween(0, 3);
            }
            $obtenido = array_sum($respuestas);

            return [
                'tipo_respondente' => 'apoderado',
                'con_jefatura' => $conJefatura,
                'respuestas' => $respuestas,
                'puntaje_obtenido' => $obtenido,
                'puntaje_maximo' => $totalPreguntas * 3,
                'promedio' => round($obtenido / $totalPreguntas, 2),
            ];
        });
    }
}
