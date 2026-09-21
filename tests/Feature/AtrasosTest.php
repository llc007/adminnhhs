<?php

use App\Models\Atraso;
use App\Models\Estudiante;
use App\Models\User;
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
    Role::findOrCreate($role, 'web');
    $user->syncRolesForSchool($schoolId, [$role]);

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
