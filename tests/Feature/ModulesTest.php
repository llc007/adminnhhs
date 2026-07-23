<?php

use App\Listeners\FilterNotificationEmails;
use App\Models\Entrevista;
use App\Models\Estudiante;
use App\Models\School;
use App\Models\User;
use App\Notifications\EntrevistaAgendadaApoderado;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('guests are redirected to the login page from modules management', function () {
    $response = $this->get(route('admin.modules'));
    $response->assertRedirect(route('login'));
});

test('regular teachers cannot visit the modules management page', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['docente']);

    $this->actingAs($user);

    $response = $this->get(route('admin.modules'));
    $response->assertStatus(403);
});

test('superadmin can visit the modules management page', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['superadmin']);

    $this->actingAs($user);

    $response = $this->get(route('admin.modules'));
    $response->assertOk();
});

test('modules configuration can be updated by superadmin', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['superadmin']);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.modules')
        ->set('modulos.entrevistas', false)
        ->set('modulos.estudiantes', true)
        ->set('modulos.adquisiciones', false)
        ->set('modulos.prestamos', true)
        ->set('modulos.envio_correos', false)
        ->call('save');

    $response->assertHasNoErrors();

    $school = School::find($schoolId);
    expect($school->modulos_publicados)->toEqual([
        'entrevistas' => false,
        'estudiantes' => true,
        'adquisiciones' => false,
        'prestamos' => true,
        'envio_correos' => false,
    ]);
});

test('email sending configuration can be toggled from mail logs page', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['superadmin']);

    $this->actingAs($user);

    $school = School::find($schoolId);
    expect($school->emailsEnabled())->toBeTrue();

    // Toggle to false
    Livewire::test('pages::admin.mail_logs')
        ->set('envioCorreosHabilitado', false);

    $school->refresh();
    expect($school->emailsEnabled())->toBeFalse();

    // Toggle back to true
    Livewire::test('pages::admin.mail_logs')
        ->set('envioCorreosHabilitado', true);

    $school->refresh();
    expect($school->emailsEnabled())->toBeTrue();
});

test('notifications on the mail channel are cancelled when envio_correos is disabled', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['superadmin']);

    $school = School::find($schoolId);

    $studentUser = User::factory()->create();
    $estudiante = Estudiante::create([
        'user_id' => $studentUser->id,
        'school_id' => $schoolId,
        'email' => 'student@test.com',
        'nombres_csv' => 'STUDENT NAME',
    ]);

    // Create a mock interview and notification
    $entrevista = Entrevista::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'user_id' => $user->id,
        'fecha' => now()->toDateString(),
        'hora' => '10:00:00',
        'urgencia' => 'baja',
        'motivo' => 'Reunion',
        'estado' => 'pendiente',
    ]);

    $notification = new EntrevistaAgendadaApoderado($entrevista);
    $listener = new FilterNotificationEmails;

    // Case 1: envio_correos is true (default)
    $event = new NotificationSending($user, $notification, 'mail');
    $result = $listener->handle($event);
    expect($result)->toBeNull(); // Should not cancel (returns null to proceed)

    // Case 2: envio_correos is false
    $modulos = $school->modulos_publicados;
    $modulos['envio_correos'] = false;
    $school->modulos_publicados = $modulos;
    $school->save();

    $result = $listener->handle($event);
    expect($result)->toBeFalse(); // Should cancel (returns false)

    // Case 3: other channels (e.g. database) should not be cancelled even if emails are disabled
    $databaseEvent = new NotificationSending($user, $notification, 'database');
    $databaseResult = $listener->handle($databaseEvent);
    expect($databaseResult)->toBeNull(); // Should not cancel (returns null)
});

test('role switcher component can toggle user roles in development', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['docente']);

    $this->actingAs($user);

    // Initial role is docente
    expect($user->active_roles)->toEqual(['docente']);

    // Toggle 'ti' role
    Livewire::test('layout.role-switcher')
        ->call('toggleRole', 'ti');

    $user->refresh();
    // It should now contain both docente and ti
    expect($user->active_roles)->toContain('ti');
    expect($user->active_roles)->toContain('docente');

    // Toggle 'ti' role again to remove it
    Livewire::test('layout.role-switcher')
        ->call('toggleRole', 'ti');

    $user->refresh();
    expect($user->active_roles)->not->toContain('ti');
    expect($user->active_roles)->toContain('docente');
});

test('unauthorized users cannot visit roles and permissions manager', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['docente']);

    $this->actingAs($user);

    $response = $this->get(route('admin.roles_permissions'));
    $response->assertStatus(403);
});

test('superadmin can load and save role permissions', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['superadmin']);

    $this->actingAs($user);

    $response = $this->get(route('admin.roles_permissions'));
    $response->assertOk();

    // Check we can toggle permission
    $role = Role::where('team_id', $schoolId)->where('name', 'docente')->first();
    expect($role)->not->toBeNull();

    Livewire::test('pages::admin.roles_permissions')
        ->call('selectRole', $role->id)
        ->set('permisosSeleccionados', ['ver-estudiantes', 'crear-entrevistas'])
        ->call('guardar');

    $role->refresh();
    expect($role->hasPermissionTo('ver-estudiantes'))->toBeTrue();
    expect($role->hasPermissionTo('crear-entrevistas'))->toBeTrue();
    expect($role->hasPermissionTo('gestionar-funcionarios'))->toBeFalse();
});
