<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear el colegio
        $school = School::firstOrCreate(
            ['domain' => 'newheavenhs.cl'],
            ['name' => 'New Heaven High School']
        );
        $this->command->info("🏫 Colegio: {$school->name}");

        // 2. Crear el año académico 2026
        $year = AcademicYear::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Año Académico 2026'],
            [
                'start_date' => '2026-03-01',
                'end_date'   => '2026-12-15',
                'is_active'  => true,
            ]
        );
        $this->command->info("📅 Año académico: {$year->name}");

        // 3. Crear el 1° Semestre 2026
        AcademicTerm::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '1° Semestre 2026'],
            [
                'start_date' => '2026-03-01',
                'end_date'   => '2026-07-04',
            ]
        );
        $this->command->info('📆 Término: 1° Semestre 2026');

        // 4. Crear el 2° Semestre 2026 (referencial)
        AcademicTerm::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '2° Semestre 2026'],
            [
                'start_date' => '2026-07-27',
                'end_date'   => '2026-12-15',
            ]
        );
        $this->command->info('📆 Término: 2° Semestre 2026');

        // 5. Asociar user id=1 al colegio con roles administrador y docente
        $user = User::find(1);

        if (! $user) {
            $this->command->warn('⚠️  No se encontró el usuario con id=1. Omitiendo asociación.');

            return;
        }

        // Actualizar current_school_id del usuario
        $user->update(['current_school_id' => $school->id]);

        // Adjuntar al colegio con roles (o actualizar si ya existe)
        if ($user->schools()->where('school_id', $school->id)->exists()) {
            $user->schools()->updateExistingPivot($school->id, [
                'roles' => json_encode(['administrador', 'docente']),
            ]);
        } else {
            $user->schools()->attach($school->id, [
                'roles' => json_encode(['administrador', 'docente']),
            ]);
        }

        $this->command->info("👤 Usuario id=1 ({$user->nombres}) asociado con roles: administrador, docente");
    }
}
