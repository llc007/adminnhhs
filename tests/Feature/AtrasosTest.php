<?php

use App\Models\Atraso;
use App\Models\Estudiante;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function setupAtrasosEnvironment(string $role = 'inspector')
{
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Colegio Test Atrasos',
        'domain' => 'testcolegio.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => '2026',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cursoId = DB::table('cursos')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'nivel' => 3,
        'modalidad' => 'media',
        'letra' => 'B',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    $roleModel = Role::findOrCreate($role, 'web');
    $user->syncRolesForSchool($schoolId, [$role]);

    $perm = Permission::findOrCreate('ingresar-atrasos', 'web');
    if ($role !== 'docente') {
        $roleModel->givePermissionTo($perm);
    }

    $estudiante = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'JUAN PEREZ GONZALEZ',
        'rut_numero' => '21444555',
        'rut_dv' => '7',
        'estado' => 'activo',
    ]);

    return [$user, $schoolId, $cursoId, $estudiante];
}

test('authorized users can access the atrasos index page', function () {
    [$user] = setupAtrasosEnvironment('inspector');

    $this->actingAs($user)
        ->get(route('atrasos.index'))
        ->assertOk();
});

test('clicking student registers atraso directly and clicking again opens annul confirmation modal', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    $this->actingAs($user);

    $component = Livewire::test('pages::atrasos.index')
        ->call('clickEstudiante', $estudiante->id)
        ->assertSet('modalEliminar', false);

    expect(Atraso::where('school_id', $schoolId)->where('estudiante_id', $estudiante->id)->count())->toBe(1);

    $atraso = Atraso::where('estudiante_id', $estudiante->id)->first();
    expect($atraso->estado)->toBe('injustificado')
        ->and($atraso->curso_id)->toBe($cursoId)
        ->and($atraso->registrado_por_user_id)->toBe($user->id);

    // Clicking again opens annul modal
    $component->call('clickEstudiante', $estudiante->id)
        ->assertSet('modalEliminar', true)
        ->assertSet('atrasoAEliminarId', $atraso->id);
});

test('can register an atraso for a student via livewire', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    $this->actingAs($user);

    Livewire::test('pages::atrasos.index')
        ->call('registrarAtraso', $estudiante->id);

    expect(Atraso::where('school_id', $schoolId)->where('estudiante_id', $estudiante->id)->count())->toBe(1);

    $atraso = Atraso::where('estudiante_id', $estudiante->id)->first();
    expect($atraso->estado)->toBe('injustificado')
        ->and($atraso->curso_id)->toBe($cursoId)
        ->and($atraso->registrado_por_user_id)->toBe($user->id);
});

test('prevents registering duplicate atraso on the same day', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    $this->actingAs($user);

    $component = Livewire::test('pages::atrasos.index')
        ->call('registrarAtraso', $estudiante->id);

    expect(Atraso::where('estudiante_id', $estudiante->id)->count())->toBe(1);

    // Call again on the same day
    $component->call('registrarAtraso', $estudiante->id);

    // Count should still be 1
    expect(Atraso::where('estudiante_id', $estudiante->id)->count())->toBe(1);
});

test('pressing enter in search registers first result and annulment removes it', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    $this->actingAs($user);

    $component = Livewire::test('pages::atrasos.index')
        ->set('search', 'JUAN')
        ->call('registrarPrimerResultado');

    expect(Atraso::where('school_id', $schoolId)->where('estudiante_id', $estudiante->id)->count())->toBe(1);

    $atraso = Atraso::where('estudiante_id', $estudiante->id)->first();

    // Clicking the registered student opens annul confirmation modal
    $component->call('clickEstudiante', $estudiante->id)
        ->assertSet('modalEliminar', true)
        ->call('eliminarAtraso');

    expect(Atraso::find($atraso->id))->toBeNull();
});

test('can toggle justificado status and delete an atraso', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('administrador');

    $this->actingAs($user);

    Livewire::test('pages::atrasos.index')
        ->call('registrarAtraso', $estudiante->id);

    $atraso = Atraso::where('estudiante_id', $estudiante->id)->first();
    expect($atraso->estado)->toBe('injustificado');

    // Toggle to justificado
    Livewire::test('pages::atrasos.index')
        ->call('toggleJustificado', $atraso->id);

    expect($atraso->fresh()->estado)->toBe('justificado');

    // Delete atraso
    Livewire::test('pages::atrasos.index')
        ->call('eliminarAtraso', $atraso->id);

    expect(Atraso::find($atraso->id))->toBeNull();
});

test('can view thermal ticket page', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    $atraso = Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => now('America/Santiago')->format('Y-m-d'),
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'estado' => 'injustificado',
    ]);

    $this->actingAs($user)
        ->get(route('atrasos.ticket', $atraso->id))
        ->assertOk()
        ->assertSee('PASE DE INGRESO')
        ->assertSee('JUAN PEREZ GONZALEZ');
});

test('user with ingresar-atrasos permission can access atrasos index page', function () {
    [$user, $schoolId] = setupAtrasosEnvironment('docente'); // docente does not have atrasos access by default
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    Permission::findOrCreate('ingresar-atrasos', 'web');
    $user->givePermissionTo('ingresar-atrasos');

    $this->actingAs($user)
        ->get(route('atrasos.index'))
        ->assertOk();
});

test('user with ver-atrasos permission alone cannot access registrar atrasos module', function () {
    [$user, $schoolId] = setupAtrasosEnvironment('docente');
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    Permission::findOrCreate('ver-atrasos', 'web');
    $user->givePermissionTo('ver-atrasos');

    $this->actingAs($user)
        ->get(route('atrasos.index'))
        ->assertForbidden();
});

test('directivo role without ingresar-atrasos permission cannot access atrasos module', function () {
    [$user, $schoolId] = setupAtrasosEnvironment('directivo');
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    $roleModel = Role::where('name', 'directivo')->where('team_id', $schoolId)->first();
    $roleModel->revokePermissionTo('ingresar-atrasos');

    $this->actingAs($user)
        ->get(route('atrasos.index'))
        ->assertForbidden();

    $this->actingAs($user);
    Livewire::test('pages::atrasos.index')
        ->assertForbidden();
});

test('user with ver-atrasos permission can access atrasos historial page', function () {
    [$user, $schoolId] = setupAtrasosEnvironment('docente');
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    Permission::findOrCreate('ver-atrasos', 'web');
    $user->givePermissionTo('ver-atrasos');

    $this->actingAs($user)
        ->get(route('atrasos.historial'))
        ->assertOk();
});

test('user without permissions cannot access atrasos historial page', function () {
    [$user, $schoolId] = setupAtrasosEnvironment('docente');
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

    $this->actingAs($user)
        ->get(route('atrasos.historial'))
        ->assertForbidden();
});

test('can filter atrasos by temporal period and search in historial', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    $hoy = now('America/Santiago')->toDateString();

    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $hoy,
        'hora' => '08:10:00',
        'minutos_atraso' => 10,
        'estado' => 'injustificado',
    ]);

    Livewire::test('pages::atrasos.historial')
        ->set('fecha', $hoy)
        ->set('filtroTemporal', 'dia')
        ->assertSee('JUAN PEREZ GONZALEZ')
        ->set('search', 'NONEXISTENT')
        ->assertDontSee('JUAN PEREZ GONZALEZ')
        ->set('search', '21444555')
        ->assertSee('JUAN PEREZ GONZALEZ');
});

test('can open student history modal and view past atrasos', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => now('America/Santiago')->toDateString(),
        'hora' => '08:20:00',
        'minutos_atraso' => 20,
        'estado' => 'injustificado',
    ]);

    Livewire::test('pages::atrasos.historial')
        ->call('verHistorialEstudiante', $estudiante->id)
        ->assertSet('modalDetalleEstudiante', true)
        ->assertSet('estudianteSeleccionadoId', $estudiante->id)
        ->assertSee('JUAN PEREZ GONZALEZ')
        ->assertSee('Cronograma de Atrasos Registrados');
});

test('can toggle justificado and edit atraso in historial', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    $atraso = Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => now('America/Santiago')->toDateString(),
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'estado' => 'injustificado',
    ]);

    Livewire::test('pages::atrasos.historial')
        ->call('justificarAtraso', $atraso->id)
        ->assertSet('modalEdicion', true)
        ->assertSet('editarEstado', 'justificado')
        ->set('editarMotivo', 'Certificado médico')
        ->call('guardarEdicion');

    expect($atraso->fresh()->estado)->toBe('justificado')
        ->and($atraso->fresh()->motivo)->toBe('Certificado médico');
});

test('can delete atraso in historial', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    $atraso = Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => now('America/Santiago')->toDateString(),
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'estado' => 'injustificado',
    ]);

    Livewire::test('pages::atrasos.historial')
        ->call('confirmarEliminacion', $atraso->id)
        ->assertSet('modalEliminar', true)
        ->call('eliminarAtraso');

    expect(Atraso::find($atraso->id))->toBeNull();
});

test('can export atrasos to csv from historial', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => now('America/Santiago')->toDateString(),
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'estado' => 'injustificado',
    ]);

    Livewire::test('pages::atrasos.historial')
        ->call('exportarCsv')
        ->assertFileDownloaded();
});

test('historial updates in real-time when a new atraso is registered and supports autoRefresh toggle', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    $component = Livewire::test('pages::atrasos.historial');
    $component->assertSee('En vivo')
        ->assertSet('autoRefresh', true)
        ->assertDontSee('JUAN PEREZ GONZALEZ');

    // Simulate another user / inspector registering an atraso in database
    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => now('America/Santiago')->toDateString(),
        'hora' => '08:05:00',
        'minutos_atraso' => 5,
        'estado' => 'injustificado',
    ]);

    // Livewire poll refreshes component
    $component->call('$refresh')
        ->assertSee('JUAN PEREZ GONZALEZ')
        ->assertSee('+5 min');

    // Toggle autoRefresh
    $component->call('toggleAutoRefresh')
        ->assertSet('autoRefresh', false)
        ->assertSee('En vivo pausado');
});

test('historial can filter by monthly late arrivals count', function () {
    [$user, $schoolId, $cursoId, $estudiante1] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    // Create a 2nd student
    $estudiante2 = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'MARIA LOPEZ SILVA',
        'rut_numero' => '22555666',
        'rut_dv' => '8',
        'estado' => 'activo',
    ]);

    $today = now('America/Santiago')->toDateString();

    // Student 1 has 1 atraso this month
    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante1->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:10:00',
        'minutos_atraso' => 10,
        'estado' => 'injustificado',
    ]);

    // Student 2 has 2 atrasos this month
    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante2->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'estado' => 'injustificado',
    ]);
    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante2->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:20:00',
        'minutos_atraso' => 20,
        'estado' => 'injustificado',
    ]);

    // Filter 1er atraso: should only show Student 1
    Livewire::test('pages::atrasos.historial')
        ->set('filtroTemporal', 'mes')
        ->set('filtroAtrasosMes', '1')
        ->assertSee('JUAN PEREZ GONZALEZ')
        ->assertDontSee('MARIA LOPEZ SILVA');

    // Filter 2do atraso: should only show Student 2
    Livewire::test('pages::atrasos.historial')
        ->set('filtroTemporal', 'mes')
        ->set('filtroAtrasosMes', '2')
        ->assertSee('MARIA LOPEZ SILVA')
        ->assertDontSee('JUAN PEREZ GONZALEZ');

    // Filter 3+ atrasos: none should match yet
    Livewire::test('pages::atrasos.historial')
        ->set('filtroTemporal', 'mes')
        ->set('filtroAtrasosMes', '3+')
        ->assertDontSee('JUAN PEREZ GONZALEZ')
        ->assertDontSee('MARIA LOPEZ SILVA');

    // Add a 3rd atraso for Student 2
    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante2->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:25:00',
        'minutos_atraso' => 25,
        'estado' => 'injustificado',
    ]);

    // Filter 3+ atrasos: now Student 2 matches
    Livewire::test('pages::atrasos.historial')
        ->set('filtroTemporal', 'mes')
        ->set('filtroAtrasosMes', '3+')
        ->assertSee('MARIA LOPEZ SILVA')
        ->assertDontSee('JUAN PEREZ GONZALEZ');
});

test('historial can sort table by monthly late arrivals count', function () {
    [$user, $schoolId, $cursoId, $estudiante1] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    $estudiante2 = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'MARIA LOPEZ SILVA',
        'rut_numero' => '22555666',
        'rut_dv' => '8',
        'estado' => 'activo',
    ]);

    $today = now('America/Santiago')->toDateString();

    // Student 1 has 1 atraso
    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante1->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:10:00',
        'minutos_atraso' => 10,
        'estado' => 'injustificado',
    ]);

    // Student 2 has 3 atrasos
    for ($i = 0; $i < 3; $i++) {
        Atraso::create([
            'school_id' => $schoolId,
            'estudiante_id' => $estudiante2->id,
            'curso_id' => $cursoId,
            'registrado_por_user_id' => $user->id,
            'fecha' => $today,
            'hora' => '08:15:00',
            'minutos_atraso' => 15,
            'estado' => 'injustificado',
        ]);
    }

    // Sort by atrasos_mes DESC (default direction when clicking atrasos_mes)
    $component = Livewire::test('pages::atrasos.historial')
        ->set('filtroTemporal', 'mes')
        ->call('sort', 'atrasos_mes')
        ->assertSet('sortBy', 'atrasos_mes')
        ->assertSet('sortDirection', 'desc');

    $atrasos = $component->viewData('atrasos');
    expect($atrasos->first()->estudiante_id)->toBe($estudiante2->id)
        ->and($atrasos->first()->atrasos_mes_count)->toBe(3);

    // Toggle to ASC
    $component->call('sort', 'atrasos_mes')
        ->assertSet('sortDirection', 'asc');

    $atrasosAsc = $component->viewData('atrasos');
    expect($atrasosAsc->first()->estudiante_id)->toBe($estudiante1->id)
        ->and($atrasosAsc->first()->atrasos_mes_count)->toBe(1);
});

test('can register afternoon shift atraso and calculates minutes against 13:30', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    Carbon::setTestNow(Carbon::parse('2026-09-23 13:45:00', 'America/Santiago'));

    $this->actingAs($user);

    $component = Livewire::test('pages::atrasos.index');

    // Auto-detection sets jornadaActiva to 'tarde' because current time >= 13:30
    expect($component->get('jornadaActiva'))->toBe('tarde');

    // Also verify manual toggle works
    $component->call('setJornada', 'tarde')
        ->assertSet('jornadaManual', 'tarde');

    // Register atraso at 13:45 (15 minutes after 13:30)
    $component->call('registrarAtraso', $estudiante->id);

    $atraso = Atraso::where('estudiante_id', $estudiante->id)->first();
    expect($atraso)->not->toBeNull()
        ->and($atraso->jornada)->toBe('tarde')
        ->and($atraso->minutos_atraso)->toBe(15)
        ->and($atraso->horaEntradaEsperada())->toBe('13:30');

    Carbon::setTestNow(); // Reset test now
});

test('can filter historial by jornada', function () {
    [$user, $schoolId, $cursoId, $estudiante1] = setupAtrasosEnvironment('inspector');

    $estudiante2 = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'MARIA LOPEZ PEREZ',
        'rut_numero' => '22555666',
        'rut_dv' => '9',
        'estado' => 'activo',
    ]);

    $today = now('America/Santiago')->toDateString();

    $atrasoManana = Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante1->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'jornada' => 'manana',
        'estado' => 'injustificado',
    ]);

    $atrasoTarde = Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante2->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '13:40:00',
        'minutos_atraso' => 10,
        'jornada' => 'tarde',
        'estado' => 'injustificado',
    ]);

    $this->actingAs($user);

    // Filter by 'tarde'
    $componentTarde = Livewire::test('pages::atrasos.historial')
        ->set('jornada', 'tarde');

    $recordsTarde = $componentTarde->viewData('atrasos');
    expect($recordsTarde->total())->toBe(1)
        ->and($recordsTarde->first()->id)->toBe($atrasoTarde->id);

    // Filter by 'manana'
    $componentManana = Livewire::test('pages::atrasos.historial')
        ->set('jornada', 'manana');

    $recordsManana = $componentManana->viewData('atrasos');
    expect($recordsManana->total())->toBe(1)
        ->and($recordsManana->first()->id)->toBe($atrasoManana->id);

    // Clear filter
    $componentAll = Livewire::test('pages::atrasos.historial')
        ->set('jornada', '');

    $recordsAll = $componentAll->viewData('atrasos');
    expect($recordsAll->total())->toBe(2);
});

test('can open modal and register new justified atraso directly from historial', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    $this->actingAs($user);

    $component = Livewire::test('pages::atrasos.historial')
        ->call('abrirModalNuevoAtraso')
        ->assertSet('modalNuevoAtraso', true)
        ->assertSet('nuevoEstado', 'justificado')
        ->set('nuevoSearchEstudiante', 'PEREZ');

    expect($component->get('resultadosNuevoEstudiante')->count())->toBe(1);

    $component->call('seleccionarNuevoEstudiante', $estudiante->id)
        ->assertSet('nuevoEstudianteId', $estudiante->id)
        ->set('nuevaHora', '08:45')
        ->set('nuevaJornada', 'manana')
        ->set('nuevoMotivo', 'Apoderado presente en portería')
        ->call('guardarNuevoAtraso', false)
        ->assertSet('modalNuevoAtraso', false);

    $atraso = Atraso::where('school_id', $schoolId)->where('estudiante_id', $estudiante->id)->first();
    expect($atraso)->not->toBeNull()
        ->and($atraso->estado)->toBe('justificado')
        ->and($atraso->motivo)->toBe('Apoderado presente en portería')
        ->and($atraso->minutos_atraso)->toBe(45)
        ->and($atraso->jornada)->toBe('manana')
        ->and($atraso->registrado_por_user_id)->toBe($user->id);
});

test('cannot register duplicate atraso for same student on same date from historial', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    $today = now('America/Santiago')->toDateString();

    Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:10:00',
        'minutos_atraso' => 10,
        'jornada' => 'manana',
        'estado' => 'injustificado',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::atrasos.historial')
        ->call('abrirModalNuevoAtraso')
        ->call('seleccionarNuevoEstudiante', $estudiante->id)
        ->set('nuevaFecha', $today)
        ->set('nuevaHora', '09:00')
        ->call('guardarNuevoAtraso', false);

    expect(Atraso::where('estudiante_id', $estudiante->id)->whereDate('fecha', $today)->count())->toBe(1);
});

test('can edit atraso fecha and hora in historial and recalculates minutes', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');
    $this->actingAs($user);

    $atraso = Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => '2026-09-23',
        'hora' => '08:10:00',
        'minutos_atraso' => 10,
        'jornada' => 'manana',
        'estado' => 'injustificado',
    ]);

    Livewire::test('pages::atrasos.historial')
        ->call('abrirModalEdicion', $atraso->id)
        ->assertSet('modalEdicion', true)
        ->assertSet('editarFecha', '2026-09-23')
        ->assertSet('editarHora', '08:10')
        ->set('editarFecha', '2026-09-24')
        ->set('editarHora', '08:45')
        ->set('editarMotivo', 'Problema de locomoción')
        ->call('guardarEdicion')
        ->assertSet('modalEdicion', false);

    $updated = $atraso->fresh();
    expect($updated->fecha->toDateString())->toBe('2026-09-24')
        ->and($updated->hora)->toBe('08:45:00')
        ->and($updated->minutos_atraso)->toBe(45)
        ->and($updated->motivo)->toBe('Problema de locomoción');
});

test('can filter historial by ciclo (basica or media)', function () {
    [$user, $schoolId, $cursoMediaId, $estudianteMedia] = setupAtrasosEnvironment('inspector');

    // Create a basica course and student
    $cursoBasicaId = DB::table('cursos')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => DB::table('academic_years')->where('school_id', $schoolId)->value('id'),
        'nivel' => 5,
        'modalidad' => 'basica',
        'letra' => 'A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $estudianteBasica = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoBasicaId,
        'nombres_csv' => 'PEDRO PICAPIEDRA',
        'rut_numero' => '23111222',
        'rut_dv' => '3',
        'estado' => 'activo',
    ]);

    $today = now('America/Santiago')->toDateString();

    $atrasoMedia = Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudianteMedia->id,
        'curso_id' => $cursoMediaId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'jornada' => 'manana',
        'estado' => 'injustificado',
    ]);

    $atrasoBasica = Atraso::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudianteBasica->id,
        'curso_id' => $cursoBasicaId,
        'registrado_por_user_id' => $user->id,
        'fecha' => $today,
        'hora' => '08:20:00',
        'minutos_atraso' => 20,
        'jornada' => 'manana',
        'estado' => 'injustificado',
    ]);

    $this->actingAs($user);

    // Filter by 'basica'
    $componentBasica = Livewire::test('pages::atrasos.historial')
        ->set('ciclo', 'basica');

    $recordsBasica = $componentBasica->viewData('atrasos');
    expect($recordsBasica->total())->toBe(1)
        ->and($recordsBasica->first()->id)->toBe($atrasoBasica->id);

    // Filter by 'media'
    $componentMedia = Livewire::test('pages::atrasos.historial')
        ->set('ciclo', 'media');

    $recordsMedia = $componentMedia->viewData('atrasos');
    expect($recordsMedia->total())->toBe(1)
        ->and($recordsMedia->first()->id)->toBe($atrasoMedia->id);

    // Clear ciclo filter
    $componentTodos = Livewire::test('pages::atrasos.historial')
        ->set('ciclo', '');

    $recordsTodos = $componentTodos->viewData('atrasos');
    expect($recordsTodos->total())->toBe(2);
});

test('historial student detail modal displays who registered the atraso in cronograma', function () {
    [$user, $schoolId, $cursoId, $estudiante] = setupAtrasosEnvironment('inspector');

    $user->update([
        'nombres' => 'MARIA',
        'apellido_pat' => 'RODRIGUEZ',
    ]);

    $atraso = Atraso::create([
        'school_id' => $schoolId,
        'academic_year_id' => 1,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $cursoId,
        'registrado_por_user_id' => $user->id,
        'fecha' => now('America/Santiago')->toDateString(),
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'jornada' => 'manana',
        'estado' => 'injustificado',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::atrasos.historial')
        ->call('verHistorialEstudiante', $estudiante->id)
        ->assertSet('modalDetalleEstudiante', true)
        ->assertSee('MARIA RODRIGUEZ')
        ->assertSee('Por:');
});
