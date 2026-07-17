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
                'end_date' => '2026-12-15',
                'is_active' => true,
            ]
        );
        $this->command->info("📅 Año académico: {$year->name}");

        // 3. Crear el 1° Semestre 2026
        AcademicTerm::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '1° Semestre 2026'],
            [
                'start_date' => '2026-03-01',
                'end_date' => '2026-07-04',
            ]
        );
        $this->command->info('📆 Término: 1° Semestre 2026');

        // 4. Crear el 2° Semestre 2026 (referencial)
        AcademicTerm::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '2° Semestre 2026'],
            [
                'start_date' => '2026-07-27',
                'end_date' => '2026-12-15',
            ]
        );
        $this->command->info('📆 Término: 2° Semestre 2026');

        // 5. Crear o asociar el usuario administrador inicial
        $adminEmail = env('ADMIN_EMAIL', 'luislopez@newheavenhs.cl');
        $user = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'nombres' => 'Administrador',
                'apellido_pat' => 'Principal',
                'apellido_mat' => '',
            ]
        );

        // Actualizar current_school_id del usuario
        $user->update(['current_school_id' => $school->id]);

        // Adjuntar al colegio con roles (o actualizar si ya existe)
        $user->syncRolesForSchool($school->id, ['administrador', 'docente']);

        $this->command->info("👤 Usuario administrador ({$user->email}) asociado con roles: administrador, docente");
    }
}
