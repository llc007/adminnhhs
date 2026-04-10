<?php

namespace Database\Seeders;

use App\Enums\Modalidad;
use App\Models\AcademicYear;
use App\Models\Curso;
use App\Models\School;
use Illuminate\Database\Seeder;

class CursosSeeder extends Seeder
{
    /**
     * Los cursos con su nombre exacto tal como aparece en el CSV de FullCollege.
     * Se incluyen las irregularidades del sistema (sin espacio antes del paréntesis).
     *
     * @var array<int, array{modalidad: string, nivel: int, letra: string, nombre_fc: string}>
     */
    private array $cursos = [
        // 1° Básico
        ['modalidad' => 'basica', 'nivel' => 1, 'letra' => 'A', 'nombre_fc' => '1 BASICO A (110)'],
        ['modalidad' => 'basica', 'nivel' => 1, 'letra' => 'B', 'nombre_fc' => '1 BASICO B (110)'],
        ['modalidad' => 'basica', 'nivel' => 1, 'letra' => 'C', 'nombre_fc' => '1 BASICO C (110)'],
        ['modalidad' => 'basica', 'nivel' => 1, 'letra' => 'D', 'nombre_fc' => '1 BASICO D (110)'],
        // 2° Básico
        ['modalidad' => 'basica', 'nivel' => 2, 'letra' => 'A', 'nombre_fc' => '2 BASICO A (110)'],
        ['modalidad' => 'basica', 'nivel' => 2, 'letra' => 'B', 'nombre_fc' => '2 BASICO B (110)'],
        ['modalidad' => 'basica', 'nivel' => 2, 'letra' => 'C', 'nombre_fc' => '2 BASICO C (110)'],
        ['modalidad' => 'basica', 'nivel' => 2, 'letra' => 'D', 'nombre_fc' => '2 BASICO D (110)'],
        // 3° Básico (C y D sin espacio antes del paréntesis en el CSV)
        ['modalidad' => 'basica', 'nivel' => 3, 'letra' => 'A', 'nombre_fc' => '3 BÁSICO A (110)'],
        ['modalidad' => 'basica', 'nivel' => 3, 'letra' => 'B', 'nombre_fc' => '3 BASICO B (110)'],
        ['modalidad' => 'basica', 'nivel' => 3, 'letra' => 'C', 'nombre_fc' => '3 BASICO C(110)'],
        ['modalidad' => 'basica', 'nivel' => 3, 'letra' => 'D', 'nombre_fc' => '3 BASICO D(110)'],
        // 4° Básico (C y D sin espacio)
        ['modalidad' => 'basica', 'nivel' => 4, 'letra' => 'A', 'nombre_fc' => '4 BASICO A (110)'],
        ['modalidad' => 'basica', 'nivel' => 4, 'letra' => 'B', 'nombre_fc' => '4 BASICO B (110)'],
        ['modalidad' => 'basica', 'nivel' => 4, 'letra' => 'C', 'nombre_fc' => '4 BASICO C(110)'],
        ['modalidad' => 'basica', 'nivel' => 4, 'letra' => 'D', 'nombre_fc' => '4 BASICO D(110)'],
        // 5° Básico
        ['modalidad' => 'basica', 'nivel' => 5, 'letra' => 'A', 'nombre_fc' => '5 BASICO A (110)'],
        ['modalidad' => 'basica', 'nivel' => 5, 'letra' => 'B', 'nombre_fc' => '5 BASICO B (110)'],
        ['modalidad' => 'basica', 'nivel' => 5, 'letra' => 'C', 'nombre_fc' => '5 BASICO C (110)'],
        ['modalidad' => 'basica', 'nivel' => 5, 'letra' => 'D', 'nombre_fc' => '5 BASICO D(110)'],
        // 6° Básico
        ['modalidad' => 'basica', 'nivel' => 6, 'letra' => 'A', 'nombre_fc' => '6 BÁSICO A (110)'],
        ['modalidad' => 'basica', 'nivel' => 6, 'letra' => 'B', 'nombre_fc' => '6 BÁSICO B (110)'],
        ['modalidad' => 'basica', 'nivel' => 6, 'letra' => 'C', 'nombre_fc' => '6 BÁSICO C (110)'],
        ['modalidad' => 'basica', 'nivel' => 6, 'letra' => 'D', 'nombre_fc' => '6 BÁSICO D (110)'],
        // 7° Básico
        ['modalidad' => 'basica', 'nivel' => 7, 'letra' => 'A', 'nombre_fc' => '7 BÁSICO A (110)'],
        ['modalidad' => 'basica', 'nivel' => 7, 'letra' => 'B', 'nombre_fc' => '7 BÁSICO B (110)'],
        ['modalidad' => 'basica', 'nivel' => 7, 'letra' => 'C', 'nombre_fc' => '7 BÁSICO C (110)'],
        ['modalidad' => 'basica', 'nivel' => 7, 'letra' => 'D', 'nombre_fc' => '7 BÁSICO D (110)'],
        // 8° Básico (tiene 5 paralelos)
        ['modalidad' => 'basica', 'nivel' => 8, 'letra' => 'A', 'nombre_fc' => '8 BÁSICO A (110)'],
        ['modalidad' => 'basica', 'nivel' => 8, 'letra' => 'B', 'nombre_fc' => '8 BÁSICO B (110)'],
        ['modalidad' => 'basica', 'nivel' => 8, 'letra' => 'C', 'nombre_fc' => '8 BÁSICO C (110)'],
        ['modalidad' => 'basica', 'nivel' => 8, 'letra' => 'D', 'nombre_fc' => '8 BÁSICO D (110)'],
        ['modalidad' => 'basica', 'nivel' => 8, 'letra' => 'E', 'nombre_fc' => '8 BÁSICO E (110)'],
        // 1° Medio (5 paralelos)
        ['modalidad' => 'media', 'nivel' => 1, 'letra' => 'A', 'nombre_fc' => '1 MEDIO A (310)'],
        ['modalidad' => 'media', 'nivel' => 1, 'letra' => 'B', 'nombre_fc' => '1 MEDIO B (310)'],
        ['modalidad' => 'media', 'nivel' => 1, 'letra' => 'C', 'nombre_fc' => '1 MEDIO C (310)'],
        ['modalidad' => 'media', 'nivel' => 1, 'letra' => 'D', 'nombre_fc' => '1 MEDIO D (310)'],
        ['modalidad' => 'media', 'nivel' => 1, 'letra' => 'E', 'nombre_fc' => '1 MEDIO E (310)'],
        // 2° Medio (5 paralelos)
        ['modalidad' => 'media', 'nivel' => 2, 'letra' => 'A', 'nombre_fc' => '2 MEDIO A (310)'],
        ['modalidad' => 'media', 'nivel' => 2, 'letra' => 'B', 'nombre_fc' => '2 MEDIO B (310)'],
        ['modalidad' => 'media', 'nivel' => 2, 'letra' => 'C', 'nombre_fc' => '2 MEDIO C (310)'],
        ['modalidad' => 'media', 'nivel' => 2, 'letra' => 'D', 'nombre_fc' => '2 MEDIO D (310)'],
        ['modalidad' => 'media', 'nivel' => 2, 'letra' => 'E', 'nombre_fc' => '2 MEDIO E (310)'],
        // 3° Medio (3 paralelos)
        ['modalidad' => 'media', 'nivel' => 3, 'letra' => 'A', 'nombre_fc' => '3 MEDIO A (310)'],
        ['modalidad' => 'media', 'nivel' => 3, 'letra' => 'B', 'nombre_fc' => '3 MEDIO B (310)'],
        ['modalidad' => 'media', 'nivel' => 3, 'letra' => 'C', 'nombre_fc' => '3 MEDIO C (310)'],
        // 4° Medio (3 paralelos)
        ['modalidad' => 'media', 'nivel' => 4, 'letra' => 'A', 'nombre_fc' => '4 MEDIO A (310)'],
        ['modalidad' => 'media', 'nivel' => 4, 'letra' => 'B', 'nombre_fc' => '4 MEDIO B (310)'],
        ['modalidad' => 'media', 'nivel' => 4, 'letra' => 'C', 'nombre_fc' => '4 MEDIO C (310)'],
    ];

    public function run(): void
    {
        // Obtener el primer colegio y el año académico activo
        $school = School::firstOrFail();
        $academicYear = AcademicYear::where('school_id', $school->id)
            ->orderByDesc('start_date')
            ->firstOrFail();

        $this->command->info("Creando cursos para: {$school->name} — {$academicYear->name}");

        $creados = 0;
        $omitidos = 0;

        foreach ($this->cursos as $datos) {
            $existe = Curso::where('school_id', $school->id)
                ->where('academic_year_id', $academicYear->id)
                ->where('modalidad', $datos['modalidad'])
                ->where('nivel', $datos['nivel'])
                ->where('letra', $datos['letra'])
                ->exists();

            if ($existe) {
                $omitidos++;
                continue;
            }

            Curso::create([
                'school_id'       => $school->id,
                'academic_year_id' => $academicYear->id,
                'modalidad'       => Modalidad::from($datos['modalidad']),
                'nivel'           => $datos['nivel'],
                'letra'           => $datos['letra'],
                'nombre_fc'       => $datos['nombre_fc'],
            ]);

            $creados++;
        }

        $this->command->info("✅ {$creados} cursos creados. {$omitidos} ya existían.");
    }
}
