<?php

use App\Models\Curso;
use App\Models\Entrevista;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['administrador']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated assistants are redirected to mis prestamos', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['asistente']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('ti.prestamos.mis_prestamos'));
});

test('entrevistas dashboard displays top 10 docentes and monthly comparison', function () {
    $user = User::factory()->create([
        'nombres' => 'Carlos',
        'apellido_pat' => 'Rojas',
    ]);

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['administrador']);

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $curso = Curso::create([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'modalidad' => 'basica',
        'nivel' => 8,
        'letra' => 'A',
    ]);

    $estudiante = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $curso->id,
        'rut_numero' => 21111222,
        'rut_dv' => '3',
        'nombres_csv' => 'MATEO GONZALEZ SILVA',
    ]);

    Entrevista::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'user_id' => $user->id,
        'fecha' => now('America/Santiago')->format('Y-m-d'),
        'hora' => '09:30:00',
        'urgencia' => 'prioritario',
        'motivo' => 'Rendimiento académico bajo',
        'estado' => 'ingresada',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::entrevistas.dashboard')
        ->assertSet('kpiHoy', 1)
        ->assertSet('kpiActivos', 1)
        ->assertSee('Dashboard de Entrevistas')
        ->assertSee('Mayor volumen de atención en')
        ->assertSee('CARLOS ROJAS')
        ->assertSee('Comparativa mensual')
        ->assertSee('Rendimiento Académico');
});
