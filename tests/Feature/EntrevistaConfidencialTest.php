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
