<?php

use App\Models\Entrevista;
use App\Models\EntrevistaCompartida;
use App\Models\Estudiante;
use App\Models\School;
use App\Models\User;
use App\Notifications\EntrevistaCompartidaNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function setupEntrevistaEnv()
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $school = School::create(['name' => 'Colegio Test', 'code' => 'TEST-001', 'is_active' => true]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

    // Ensure permissions
    Permission::findOrCreate('ver-entrevistas-confidenciales', 'web');
    Permission::findOrCreate('crear-entrevistas-confidenciales', 'web');
    Permission::findOrCreate('crear-entrevistas', 'web');
    Permission::findOrCreate('ver-entrevistas-propias', 'web');
    Permission::findOrCreate('ver-bitacoras', 'web');

    $rolePsico = Role::findOrCreate('psicosocial', 'web');
    $rolePsico->givePermissionTo('ver-entrevistas-confidenciales');
    $rolePsico->givePermissionTo('crear-entrevistas-confidenciales');
    $rolePsico->givePermissionTo('crear-entrevistas');

    $roleDocente = Role::findOrCreate('docente', 'web');

    $roleSuper = Role::findOrCreate('superadmin', 'web');
    $superadmin = User::factory()->create(['current_school_id' => $school->id]);
    $superadmin->assignRole($roleSuper);

    $psicologo = User::factory()->create(['current_school_id' => $school->id]);
    $psicologo->assignRole($rolePsico);

    $docenteA = User::factory()->create(['current_school_id' => $school->id]);
    $docenteA->assignRole($roleDocente);

    $docenteB = User::factory()->create(['current_school_id' => $school->id]);
    $docenteB->assignRole($roleDocente);

    $estudiante = Estudiante::create([
        'school_id' => $school->id,
        'rut_numero' => 12345678,
        'rut_dv' => '9',
        'nombres_csv' => 'Juan Pérez',
        'apoderado_nombres' => 'Pedro Pérez',
        'apoderado_email' => 'apoderado@test.com',
    ]);

    return [$school, $superadmin, $psicologo, $docenteA, $docenteB, $estudiante];
}

test('psicologo can create confidential entrevista', function () {
    [$school, $superadmin, $psicologo, $docenteA, $docenteB, $estudiante] = setupEntrevistaEnv();

    $entrevista = Entrevista::create([
        'school_id' => $school->id,
        'user_id' => $psicologo->id,
        'estudiante_id' => $estudiante->id,
        'fecha' => now()->toDateString(),
        'hora' => '10:00',
        'urgencia' => 'normal',
        'motivo' => 'Consulta psicológica confidencial',
        'estado' => 'pendiente',
        'es_confidencial' => true,
    ]);

    expect($entrevista->es_confidencial)->toBeTrue();
});

test('unauthorized docente cannot view confidential entrevista policy', function () {
    [$school, $superadmin, $psicologo, $docenteA, $docenteB, $estudiante] = setupEntrevistaEnv();

    $entrevista = Entrevista::create([
        'school_id' => $school->id,
        'user_id' => $psicologo->id,
        'estudiante_id' => $estudiante->id,
        'fecha' => now()->toDateString(),
        'hora' => '10:00',
        'urgencia' => 'normal',
        'motivo' => 'Caso reservado',
        'estado' => 'pendiente',
        'es_confidencial' => true,
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

    expect($docenteA->can('view', $entrevista))->toBeFalse();
    expect($psicologo->can('view', $entrevista))->toBeTrue();
    expect($superadmin->can('view', $entrevista))->toBeTrue();
});

test('sharing access allows targeted docente to view confidential entrevista and sends notification', function () {
    Notification::fake();

    [$school, $superadmin, $psicologo, $docenteA, $docenteB, $estudiante] = setupEntrevistaEnv();

    $entrevista = Entrevista::create([
        'school_id' => $school->id,
        'user_id' => $psicologo->id,
        'estudiante_id' => $estudiante->id,
        'fecha' => now()->toDateString(),
        'hora' => '11:00',
        'urgencia' => 'normal',
        'motivo' => 'Caso compartido',
        'estado' => 'pendiente',
        'es_confidencial' => true,
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

    // Initially docenteA cannot view
    expect($docenteA->can('view', $entrevista))->toBeFalse();

    // Share access with docenteA
    EntrevistaCompartida::create([
        'entrevista_id' => $entrevista->id,
        'user_id' => $docenteA->id,
        'granted_by_user_id' => $psicologo->id,
    ]);

    $docenteA->notify(new EntrevistaCompartidaNotification($entrevista, $psicologo));

    // Now docenteA can view but CANNOT update
    expect($docenteA->can('view', $entrevista))->toBeTrue();
    expect($docenteA->can('update', $entrevista))->toBeFalse();

    // docenteB still cannot view or update
    expect($docenteB->can('view', $entrevista))->toBeFalse();
    expect($docenteB->can('update', $entrevista))->toBeFalse();

    Notification::assertSentTo($docenteA, EntrevistaCompartidaNotification::class);
});

test('docente without permission cannot see or create confidential switch', function () {
    [$school, $superadmin, $psicologo, $docenteA, $docenteB, $estudiante] = setupEntrevistaEnv();

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    Permission::findOrCreate('crear-entrevistas', 'web');
    $docenteA->givePermissionTo('crear-entrevistas');

    Livewire::actingAs($docenteA)
        ->test('pages::entrevistas.crear')
        ->assertDontSee('🔒 Entrevista Confidencial / Privada');

    Livewire::actingAs($psicologo)
        ->test('pages::entrevistas.crear')
        ->assertSee('🔒 Entrevista Confidencial / Privada');
});

test('usuariosDisponibles method of Bitacora page excludes students and lists all other staff members without 15 limit', function () {
    [$school, $superadmin, $psicologo, $docenteA, $docenteB, $estudiante] = setupEntrevistaEnv();

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $school->id,
        'name' => 'Academic Year 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cursoId = DB::table('cursos')->insertGetId([
        'school_id' => $school->id,
        'academic_year_id' => $academicYearId,
        'nivel' => 1,
        'modalidad' => 'media',
        'letra' => 'A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $estudiante->curso_id = $cursoId;
    $estudiante->save();
    $estudiante->refresh();

    // Create directivo user
    $roleDirectivo = Role::findOrCreate('directivo', 'web');
    $directivoUser = User::factory()->create(['current_school_id' => $school->id, 'nombres' => 'Directivo User']);
    $directivoUser->syncRolesForSchool($school->id, ['directivo']);

    // Create assistant user (should be excluded)
    $roleAsistente = Role::findOrCreate('asistente', 'web');
    $asistenteUser = User::factory()->create(['current_school_id' => $school->id, 'nombres' => 'Asistente User']);
    $asistenteUser->syncRolesForSchool($school->id, ['asistente']);

    // Create more than 15 teachers to make sure they are not limited to 15
    $teachers = [];
    for ($i = 0; $i < 20; $i++) {
        $user = User::factory()->create(['current_school_id' => $school->id, 'nombres' => "Teacher {$i}"]);
        $user->syncRolesForSchool($school->id, ['docente']);
        $teachers[] = $user;
    }

    // Create a user who is a student (has associated student record)
    Role::findOrCreate('estudiante', 'web');
    $studentUser = User::factory()->create(['current_school_id' => $school->id, 'nombres' => 'Student User']);
    $studentUser->syncRolesForSchool($school->id, ['estudiante']);
    Estudiante::create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'rut_numero' => 98765432,
        'rut_dv' => '1',
        'nombres_csv' => 'Student User',
    ]);

    // Create interview
    $entrevista = Entrevista::create([
        'school_id' => $school->id,
        'user_id' => $psicologo->id,
        'estudiante_id' => $estudiante->id,
        'fecha' => now()->toDateString(),
        'hora' => '10:00',
        'urgencia' => 'normal',
        'motivo' => 'Test',
        'estado' => 'pendiente',
    ]);

    $component = Livewire::actingAs($psicologo)
        ->test('pages::entrevistas.bitacora', ['entrevista' => $entrevista]);

    // When search input is empty, dropdown should be closed (0 results)
    $disponiblesInicial = $component->get('usuariosDisponibles');
    expect(count($disponiblesInicial))->toBe(0);

    // Live search for directivo user returns directivoUser, excludes assistant, student, and creator
    $component->set('searchUsuarioCompartir', 'Directivo');
    $searchIds = collect($component->get('usuariosDisponibles'))->pluck('id')->toArray();
    expect($searchIds)->toContain($directivoUser->id);
    expect($searchIds)->not->toContain($asistenteUser->id);
    expect($searchIds)->not->toContain($studentUser->id);
    expect($searchIds)->not->toContain($psicologo->id);

    // Selecting directivoUser sets selectedUserIdCompartir
    $component->call('seleccionarUsuarioCompartir', $directivoUser->id);
    expect($component->get('selectedUserIdCompartir'))->toBe($directivoUser->id);
});

test('user with ver-entrevistas-general can see confidential entrevista in index table but cannot view bitacora without ver-entrevistas-confidenciales', function () {
    [$school, $superadmin, $psicologo, $docenteA, $docenteB, $estudiante] = setupEntrevistaEnv();

    Permission::findOrCreate('ver-entrevistas-general', 'web');
    $docenteA->givePermissionTo('ver-entrevistas-general');

    $confidencial = Entrevista::create([
        'school_id' => $school->id,
        'user_id' => $psicologo->id,
        'estudiante_id' => $estudiante->id,
        'fecha' => now()->toDateString(),
        'hora' => '10:00',
        'urgencia' => 'normal',
        'motivo' => 'Caso reservado psicosocial',
        'estado' => 'pendiente',
        'es_confidencial' => true,
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

    // Docente A sees the interview in the index listing with Bitácora Privada tag
    Livewire::actingAs($docenteA)
        ->test('pages::entrevistas.index')
        ->assertSee('Caso reservado psicosocial')
        ->assertSee('Bitácora Privada')
        ->assertDontSee(route('entrevistas.bitacora', $confidencial->id));

    // Direct route access is forbidden for Docente A
    $response = $this->actingAs($docenteA)->get(route('entrevistas.bitacora', $confidencial->id));
    $response->assertForbidden();

    // If Docente A is granted ver-entrevistas-confidenciales, they can view bitacora
    $docenteA->givePermissionTo('ver-entrevistas-confidenciales');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $response2 = $this->actingAs($docenteA)->get(route('entrevistas.bitacora', $confidencial->id));
    $response2->assertOk();
});
