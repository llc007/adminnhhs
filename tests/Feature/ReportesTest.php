<?php

use App\Models\Entrevista;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function setupReportesEnvironment()
{
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Colegio Reportes Test',
        'domain' => 'reportestest.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $admin = User::factory()->create([
        'current_school_id' => $schoolId,
        'email' => 'admin@reportestest.cl',
    ]);
    $admin->syncRolesForSchool($schoolId, ['superadmin', 'administrador']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    $admin->givePermissionTo([
        Permission::findOrCreate('ver-reportes-entrevistas', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $docente = User::factory()->create([
        'current_school_id' => $schoolId,
        'email' => 'juan.perez@reportestest.cl',
        'nombres' => 'JUAN PABLO',
        'apellido_pat' => 'PEREZ',
        'apellido_mat' => 'GONZALEZ',
        'rut_numero' => 12345678,
        'rut_dv' => '9',
    ]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

    // Create test account that should be excluded automatically
    $testUser = User::factory()->create([
        'current_school_id' => $schoolId,
        'email' => '_docente@reportestest.cl',
        'nombres' => 'CUENTA DE PRUEBA',
    ]);
    $testUser->syncRolesForSchool($schoolId, ['docente']);

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cursoId = DB::table('cursos')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'modalidad' => 'media',
        'nivel' => 1,
        'letra' => 'A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $estudianteId = DB::table('estudiantes')->insertGetId([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'EMILY SAAVEDRA',
        'apoderado_nombres' => 'VALERIA',
        'apoderado_apellido_pat' => 'ROJAS',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create 3 interviews with various states for this docente in current year
    Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'estudiante_id' => $estudianteId,
        'fecha' => now('America/Santiago')->format('Y-m-d'),
        'hora' => '10:00:00',
        'motivo' => 'Reunión Rendimiento',
        'urgencia' => 'normal',
        'estado' => 'realizada',
    ]);

    Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'estudiante_id' => $estudianteId,
        'fecha' => now('America/Santiago')->format('Y-m-d'),
        'hora' => '11:00:00',
        'motivo' => 'Reunión Conducta',
        'urgencia' => 'normal',
        'estado' => 'cancelada',
    ]);

    Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'estudiante_id' => $estudianteId,
        'fecha' => now('America/Santiago')->format('Y-m-d'),
        'hora' => '12:00:00',
        'motivo' => 'Reunión Asistencia',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
    ]);

    return [$admin, $docente, $schoolId];
}

test('guest cannot access reportes page and is redirected', function () {
    $this->get(route('reportes.index'))
        ->assertRedirect(route('login'));
});

test('user without permissions receives 403 on reportes page', function () {
    [$admin, $docente, $schoolId] = setupReportesEnvironment();

    $unauthorizedUser = User::factory()->create([
        'current_school_id' => $schoolId,
    ]);
    $unauthorizedUser->syncRolesForSchool($schoolId, ['externo']);

    $this->actingAs($unauthorizedUser)
        ->get(route('reportes.index'))
        ->assertForbidden();
});

test('admin or directivo can access reportes page and view consolidated stats', function () {
    [$admin, $docente, $schoolId] = setupReportesEnvironment();

    $this->actingAs($admin);

    $component = Livewire::test('pages::reportes.index')
        ->assertOk()
        ->assertSee('Módulo de Reportes')
        ->assertSee('Entrevistas por Profesor')
        ->assertSee('JUAN PABLO PEREZ')
        ->assertDontSee('CUENTA DE PRUEBA');

    $docentes = $component->get('funcionarios');
    $docenteItem = collect($docentes->items())->firstWhere('id', $docente->id);
    $testItem = collect($docentes->items())->firstWhere('email', '_docente@reportestest.cl');

    expect($testItem)->toBeNull();
    expect($docenteItem)->not->toBeNull();
    expect($docenteItem->total_agendadas)->toBe(3);
    expect($docenteItem->total_realizadas)->toBe(1);
    expect($docenteItem->total_canceladas)->toBe(1);
    expect($docenteItem->total_abiertas)->toBe(1);
});

test('search filter works by name or rut', function () {
    [$admin, $docente, $schoolId] = setupReportesEnvironment();

    $this->actingAs($admin);

    Livewire::test('pages::reportes.index')
        ->set('search', 'JUAN')
        ->assertSee('JUAN PABLO PEREZ')
        ->set('search', '12345678')
        ->assertSee('JUAN PABLO PEREZ')
        ->set('search', 'INEXISTENTE')
        ->assertDontSee('JUAN PABLO PEREZ');
});

test('admin can view teacher interview detail in modal', function () {
    [$admin, $docente, $schoolId] = setupReportesEnvironment();

    $this->actingAs($admin);

    Livewire::test('pages::reportes.index')
        ->call('verDetalle', $docente->id)
        ->assertSet('modalDetalle', true)
        ->assertSee('Detalle de Entrevistas: JUAN PABLO PEREZ')
        ->assertSee('Reunión Rendimiento')
        ->assertSee('Reunión Conducta')
        ->assertSee('Reunión Asistencia');
});

test('admin can export report to csv', function () {
    [$admin, $docente, $schoolId] = setupReportesEnvironment();

    $this->actingAs($admin);

    $response = Livewire::test('pages::reportes.index')
        ->call('exportarExcel');

    expect($response->effects['download'])->not->toBeNull();
});

test('admin can access print/pdf view with official spreadsheet format', function () {
    [$admin, $docente, $schoolId] = setupReportesEnvironment();

    $this->actingAs($admin)
        ->get(route('reportes.imprimir.profesores', ['periodo' => 'ano_actual']))
        ->assertOk()
        ->assertSee('Informe Consolidado: Entrevistas por Docente / Funcionario')
        ->assertSee('Colegio Reportes Test')
        ->assertSee('JUAN PABLO PEREZ')
        ->assertSee('TOTALES CONSOLIDADOS');
});
