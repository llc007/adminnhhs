<?php

use App\Models\Entrevista;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function setupGerenciaEnvironment()
{
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Colegio Gerencia Test',
        'domain' => 'gerenciatest.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    $perm = Permission::findOrCreate('ver-dashboard-gerencia', 'web');
    $permReportes = Permission::findOrCreate('ver-reportes-entrevistas', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $gerente = User::factory()->create([
        'current_school_id' => $schoolId,
        'email' => 'gerencia@gerenciatest.cl',
        'nombres' => 'ROBERTO',
        'apellido_pat' => 'SOSTENEDOR',
    ]);
    $gerente->syncRolesForSchool($schoolId, ['gerencia']);

    $docente = User::factory()->create([
        'current_school_id' => $schoolId,
        'email' => 'docente@gerenciatest.cl',
        'nombres' => 'MARIA',
        'apellido_pat' => 'DOCENTE',
    ]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

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
        'nivel' => 2,
        'letra' => 'B',
        'jefe_id' => $docente->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $estudianteId = DB::table('estudiantes')->insertGetId([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'rut_numero' => 22333444,
        'rut_dv' => '5',
        'nombres_csv' => 'FELIPE ALUMNO PRUEBA',
        'apoderado_nombres' => 'MARIO',
        'apoderado_apellido_pat' => 'ALUMNO',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Crear algunas entrevistas
    Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'estudiante_id' => $estudianteId,
        'fecha' => '2026-05-10',
        'hora' => '10:00:00',
        'motivo' => 'Rendimiento Académico',
        'estado' => 'realizada',
    ]);

    Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'estudiante_id' => $estudianteId,
        'fecha' => '2026-06-15',
        'hora' => '11:00:00',
        'motivo' => 'Conducta y Convivencia',
        'estado' => 'ausente',
    ]);

    return [$gerente, $docente, $schoolId];
}

test('user with gerencia role can access gerencia dashboard', function () {
    [$gerente, $docente, $schoolId] = setupGerenciaEnvironment();

    $this->actingAs($gerente)
        ->get(route('direccion.dashboard'))
        ->assertOk()
        ->assertSee('Reporte Entrevistas')
        ->assertSee('Control de Gestión')
        ->assertSee('Atendidas (Realizadas)');
});

test('user with rectoria role can access dashboard and is redirected from /dashboard', function () {
    [$gerente, $docente, $schoolId] = setupGerenciaEnvironment();

    $rector = User::factory()->create([
        'current_school_id' => $schoolId,
        'email' => 'rector@gerenciatest.cl',
        'nombres' => 'CARLOS',
        'apellido_pat' => 'RECTOR',
    ]);
    $rector->syncRolesForSchool($schoolId, ['rectoria']);

    $this->actingAs($rector)
        ->get('/dashboard')
        ->assertRedirect(route('direccion.dashboard'));

    $this->actingAs($rector)
        ->get(route('direccion.dashboard'))
        ->assertOk()
        ->assertSee('Reporte Entrevistas')
        ->assertSee('Control de Gestión');
});

test('user without gerencia or directivo role receives 403 on gerencia dashboard', function () {
    [$gerente, $docente, $schoolId] = setupGerenciaEnvironment();

    $this->actingAs($docente)
        ->get(route('direccion.dashboard'))
        ->assertForbidden();
});

test('user with gerencia role visiting /dashboard is redirected to /direccion/dashboard', function () {
    [$gerente, $docente, $schoolId] = setupGerenciaEnvironment();

    $this->actingAs($gerente)
        ->get('/dashboard')
        ->assertRedirect(route('direccion.dashboard'));
});

test('visiting /gerencia/dashboard redirects to /direccion/dashboard', function () {
    [$gerente] = setupGerenciaEnvironment();

    $this->actingAs($gerente)
        ->get('/gerencia/dashboard')
        ->assertRedirect(route('direccion.dashboard'));
});

test('gerencia dashboard livewire component calculates kpis and periods accurately', function () {
    [$gerente, $docente, $schoolId] = setupGerenciaEnvironment();

    $this->actingAs($gerente);

    Livewire::test('pages::gerencia.dashboard')
        ->assertOk()
        ->assertSee('Reporte Entrevistas')
        ->assertSee('Control de Gestión')
        ->assertSet('periodo', 'ano_actual')
        ->assertSet('ciclo', 'todos')
        ->call('setPeriodo', 'primer_semestre')
        ->assertSet('periodo', 'primer_semestre')
        ->call('setCiclo', 'media')
        ->assertSet('ciclo', 'media');
});

test('user with gerencia role can access printable executive summary report', function () {
    [$gerente, $docente, $schoolId] = setupGerenciaEnvironment();

    $this->actingAs($gerente)
        ->get(route('direccion.imprimir.resumen', ['periodo' => 'ano_actual', 'ciclo' => 'todos']))
        ->assertOk()
        ->assertSee('Resumen Ejecutivo de Gestión y Entrevistas')
        ->assertSee('ROBERTO SOSTENEDOR')
        ->assertSee('Colegio Gerencia Test');
});

test('gerencia dashboard does not render monthly evolution chart and has report links and pagination', function () {
    [$gerente, $docente, $schoolId] = setupGerenciaEnvironment();

    // Crear un inspector con entrevistas para verificar que no aparezca en topDocentes
    $inspector = User::factory()->create([
        'current_school_id' => $schoolId,
        'email' => 'inspector@gerenciatest.cl',
        'nombres' => 'JUAN',
        'apellido_pat' => 'INSPECTOR',
    ]);
    $inspector->syncRolesForSchool($schoolId, ['inspector']);

    $estudianteId = DB::table('estudiantes')->where('school_id', $schoolId)->value('id');

    Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $inspector->id,
        'estudiante_id' => $estudianteId,
        'fecha' => '2026-07-10',
        'hora' => '12:00:00',
        'motivo' => 'Asistencia y Puntualidad',
        'estado' => 'realizada',
    ]);

    $this->actingAs($gerente);

    Livewire::test('pages::gerencia.dashboard')
        ->assertOk()
        ->assertDontSee('Evolución Mensual de Entrevistas')
        ->assertSee('Ver matriz completa de problemáticas por curso')
        ->assertSee('tipoReporte=problematicas_curso')
        ->assertSee('Ver reporte de Entrevistas por profesor')
        ->assertSee('tipoReporte=entrevistas_profesor')
        ->assertSee('MARIA DOCENTE')
        ->assertDontSee('JUAN INSPECTOR') // Excluido porque no es docente ni directivo
        ->assertSet('cursosPage', 1)
        ->call('nextCursosPage')
        ->call('prevCursosPage');
});

test('reportes index supports tipoReporte url parameter and gerencia access', function () {
    [$gerente, $docente, $schoolId] = setupGerenciaEnvironment();

    $this->actingAs($gerente)
        ->get(route('reportes.index', ['tipoReporte' => 'problematicas_curso']))
        ->assertOk();

    Livewire::actingAs($gerente)
        ->test('pages::reportes.index', ['tipoReporte' => 'problematicas_curso'])
        ->assertOk()
        ->assertSet('tipoReporte', 'problematicas_curso')
        ->assertSet('sortBy', 'curso');
});
