<?php

use App\Models\Entrevista;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function setupSchoolAndUser(array $roles = [], array $permissions = [])
{
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Permissions Test School',
        'domain' => 'perm-test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update([
        'current_school_id' => $schoolId,
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

    $user->syncRolesForSchool($schoolId, $roles);

    foreach ($permissions as $permName) {
        $perm = Permission::findOrCreate($permName, 'web');
        $user->givePermissionTo($perm);
    }

    // Flush Spatie's permission cache so the policy reads fresh state
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Academic Year 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cursoId = DB::table('cursos')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'nivel' => 1,
        'modalidad' => 'media',
        'letra' => 'A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = DB::table('estudiantes')->insertGetId([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'John Doe Student',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $entrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $user->id,
        'estudiante_id' => $studentId,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '10:00:00',
        'motivo' => 'Test Reunion',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
    ]);

    return [$user, $entrevista, $schoolId];
}

test('agenda page is blocked if ver-entrevistas-propias is missing', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], []);

    Livewire::actingAs($user)
        ->test('pages::entrevistas.agenda')
        ->assertStatus(403);
});

test('agenda page is accessible if ver-entrevistas-propias is active', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-propias']);

    Livewire::actingAs($user)
        ->test('pages::entrevistas.agenda')
        ->assertOk();
});

test('index page is blocked if both ver-entrevistas-general and ver-entrevistas-propias are missing', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], []);

    Livewire::actingAs($user)
        ->test('pages::entrevistas.index')
        ->assertStatus(403);
});

test('index page shows only own interviews if ver-entrevistas-propias is active but not ver-entrevistas-general', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-propias']);

    $otherUser = User::factory()->create(['current_school_id' => $schoolId]);
    $otherEntrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $otherUser->id,
        'estudiante_id' => $entrevista->estudiante_id,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '11:00:00',
        'motivo' => 'Other Reunion',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
    ]);

    $response = Livewire::actingAs($user)
        ->test('pages::entrevistas.index');

    $items = $response->viewData('entrevistas') ?? $response->get('entrevistas');
    $ids = collect($items->items())->pluck('id')->toArray();
    expect($ids)->toContain($entrevista->id);
    expect($ids)->not->toContain($otherEntrevista->id);
});

test('index page shows all interviews if ver-entrevistas-general is active', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-general']);

    $otherUser = User::factory()->create(['current_school_id' => $schoolId]);
    $otherEntrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $otherUser->id,
        'estudiante_id' => $entrevista->estudiante_id,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '11:00:00',
        'motivo' => 'Other Reunion',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
    ]);

    $response = Livewire::actingAs($user)
        ->test('pages::entrevistas.index');

    $items = $response->viewData('entrevistas') ?? $response->get('entrevistas');
    $ids = collect($items->items())->pluck('id')->toArray();
    expect($ids)->toContain($entrevista->id);
    expect($ids)->toContain($otherEntrevista->id);
});

test('viewing bitacora is allowed for owner if ver-entrevistas-propias is active', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-propias']);

    $response = $this->actingAs($user)->get(route('entrevistas.bitacora', $entrevista->id));
    $response->assertOk();
});

test('viewing someone else\'s bitacora is blocked if ver-bitacoras is missing', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-propias', 'ver-entrevistas-general']);

    $otherUser = User::factory()->create(['current_school_id' => $schoolId]);
    $otherEntrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $otherUser->id,
        'estudiante_id' => $entrevista->estudiante_id,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '11:00:00',
        'motivo' => 'Other Reunion',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
    ]);

    $response = $this->actingAs($user)->get(route('entrevistas.bitacora', $otherEntrevista->id));
    $response->assertStatus(403);
});

test('viewing someone else\'s bitacora is allowed if ver-bitacoras is active', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-general', 'ver-bitacoras']);

    $otherUser = User::factory()->create(['current_school_id' => $schoolId]);
    $otherEntrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $otherUser->id,
        'estudiante_id' => $entrevista->estudiante_id,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '11:00:00',
        'motivo' => 'Other Reunion',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
    ]);

    $response = $this->actingAs($user)->get(route('entrevistas.bitacora', $otherEntrevista->id));
    $response->assertOk();
});

test('user without eliminar-entrevistas permission cannot delete interview', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-general']);

    Livewire::actingAs($user)
        ->test('pages::entrevistas.index')
        ->call('confirmarEliminacion', $entrevista->id)
        ->assertForbidden();

    expect(Entrevista::find($entrevista->id))->not->toBeNull();
});

test('user with eliminar-entrevistas permission can delete interview', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-general', 'eliminar-entrevistas']);

    Livewire::actingAs($user)
        ->test('pages::entrevistas.index')
        ->call('confirmarEliminacion', $entrevista->id)
        ->assertSet('entrevistaIdAEliminar', $entrevista->id)
        ->call('eliminarEntrevista')
        ->assertHasNoErrors();

    expect(Entrevista::find($entrevista->id))->toBeNull();
});

test('agenda page shows only own interviews and does not show shared or other general interviews', function () {
    [$user, $entrevista, $schoolId] = setupSchoolAndUser(['docente'], ['ver-entrevistas-propias', 'ver-entrevistas-general']);

    $otherUser = User::factory()->create(['current_school_id' => $schoolId]);
    $sharedEntrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $otherUser->id,
        'estudiante_id' => $entrevista->estudiante_id,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '11:00:00',
        'motivo' => 'Shared Reunion',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
    ]);

    DB::table('entrevista_compartida')->insert([
        'entrevista_id' => $sharedEntrevista->id,
        'user_id' => $user->id,
        'granted_by_user_id' => $otherUser->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $generalEntrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $otherUser->id,
        'estudiante_id' => $entrevista->estudiante_id,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '12:00:00',
        'motivo' => 'General Reunion',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
        'es_confidencial' => false,
    ]);

    $response = Livewire::actingAs($user)
        ->test('pages::entrevistas.agenda');

    $items = $response->viewData('entrevistasLista') ?? $response->get('entrevistasLista');
    $ids = collect($items)->pluck('id')->toArray();

    expect($ids)->toContain($entrevista->id);
    expect($ids)->not->toContain($sharedEntrevista->id);
    expect($ids)->not->toContain($generalEntrevista->id);
});
