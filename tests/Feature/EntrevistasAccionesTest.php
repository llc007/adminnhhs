<?php

use App\Models\Entrevista;
use App\Models\LugarAtencion;
use App\Models\User;
use App\Notifications\EntrevistaCancelada;
use App\Notifications\IngresoApoderado;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function setupTestEnvironment()
{
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test High School',
        'domain' => 'test-high-school.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update([
        'current_school_id' => $schoolId,
    ]);

    $user->syncRolesForSchool($schoolId, ['administrador', 'docente']);

    // Grant explicit permissions since roles no longer bypass authorization
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    $user->givePermissionTo([
        Permission::findOrCreate('ver-entrevistas-general', 'web'),
        Permission::findOrCreate('ver-entrevistas-propias', 'web'),
        Permission::findOrCreate('cancelar-entrevistas', 'web'),
        Permission::findOrCreate('crear-entrevistas', 'web'),
        Permission::findOrCreate('ingresar-apoderado', 'web'),
        Permission::findOrCreate('ver-recepcion', 'web'),
    ]);
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
        'nombres_csv' => 'Jane Doe Smith',
        'rut_numero' => '12345678',
        'rut_dv' => '9',
        'apoderado_nombres' => 'John',
        'apoderado_apellido_pat' => 'Doe',
        'apoderado_email' => 'apoderado@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $entrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $user->id,
        'estudiante_id' => $studentId,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '10:00:00',
        'motivo' => 'Reunión de Apoderados',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
        'apoderado_nombre' => 'John Doe',
        'apoderado_rut' => '12345678-9',
        'apoderado_telefono' => '+56912345678',
    ]);

    return [$user, $entrevista];
}

test('it can export filtered interviews list to CSV successfully', function () {
    [$user, $entrevista] = setupTestEnvironment();

    $this->actingAs($user);

    $component = Livewire::test('pages::entrevistas.index');

    $response = $component->call('export');

    // Assert that we get a streamed response with correct headers
    $response->assertStatus(200);

    $headers = $response->instance()->effects['redirect'] ?? null;
    // Wait, streamDownload returns a BinaryFileResponse/StreamedResponse in normal requests,
    // let's check that the call completes and returns a downloadable response.
    // Livewire handles file downloads by returning a download effect or custom dispatch
    $download = $response->effects['download'] ?? null;

    expect($download)->not->toBeNull();
    expect($download['name'])->toContain('historial_entrevistas_');
});

test('it triggers EntrevistaCancelada notifications when marked as cancelada', function () {
    [$user, $entrevista] = setupTestEnvironment();

    Notification::fake();

    Livewire::actingAs($user)
        ->test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->set('estadoNoRealizada', 'cancelada')
        ->set('motivoNoRealizada', 'El apoderado avisó previamente que no asistiría.')
        ->call('marcarNoRealizada');

    // Verify the database state updated
    $entrevista->refresh();
    expect($entrevista->estado)->toBe('cancelada');

    // Verify notification was sent to the Docente (User)
    Notification::assertSentTo(
        $user,
        EntrevistaCancelada::class,
        function ($notification) {
            return $notification->destinatario === 'docente';
        }
    );

    Notification::assertSentTo(
        new AnonymousNotifiable,
        EntrevistaCancelada::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'apoderado@example.com' && $notification->destinatario === 'apoderado';
        }
    );
});

test('it triggers EntrevistaCancelada notifications when marked as ausente', function () {
    [$user, $entrevista] = setupTestEnvironment();

    Notification::fake();

    Livewire::actingAs($user)
        ->test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->set('estadoNoRealizada', 'ausente')
        ->set('motivoNoRealizada', 'El apoderado no se presentó a la cita sin dar aviso.')
        ->call('marcarNoRealizada');

    $entrevista->refresh();
    expect($entrevista->estado)->toBe('ausente');

    Notification::assertSentTo(
        $user,
        EntrevistaCancelada::class,
        function ($notification) {
            return $notification->destinatario === 'docente' && $notification->entrevista->estado === 'ausente';
        }
    );

    Notification::assertSentTo(
        new AnonymousNotifiable,
        EntrevistaCancelada::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'apoderado@example.com'
                && $notification->destinatario === 'apoderado'
                && $notification->entrevista->estado === 'ausente';
        }
    );
});

test('receptionist can add a new attention place successfully', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['recepcion']);

    $this->actingAs($user);

    Livewire::test('pages::entrevistas.recepcion')
        ->set('nuevoLugarNombre', 'BOX 99')
        ->call('guardarNuevoLugar');

    $this->assertDatabaseHas('lugares_atencion', [
        'school_id' => $schoolId,
        'nombre' => 'BOX 99',
        'activo' => true,
    ]);
});

test('receptionist can update attention place directly or set it to PENDIENTE', function () {
    [$user, $entrevista] = setupTestEnvironment();

    $this->actingAs($user);

    // 1. Update place to PENDIENTE
    Livewire::test('pages::entrevistas.recepcion')
        ->call('actualizarLugarDirecto', $entrevista->id, 'PENDIENTE')
        ->assertHasNoErrors();

    $entrevista->refresh();
    expect($entrevista->lugar)->toBe('PENDIENTE');

    // 2. Update place to a specific box
    Livewire::test('pages::entrevistas.recepcion')
        ->call('actualizarLugarDirecto', $entrevista->id, 'SALA DE REUNIONES')
        ->assertHasNoErrors();

    $entrevista->refresh();
    expect($entrevista->lugar)->toBe('SALA DE REUNIONES');
});

test('registering arrival in reception sends mail and database notification to docente', function () {
    [$user, $entrevista] = setupTestEnvironment();
    Notification::fake();

    $this->actingAs($user);

    Livewire::test('pages::entrevistas.recepcion')
        ->set('entrevistaSeleccionadaId', $entrevista->id)
        ->set('lugarIngreso', 'BOX 1')
        ->set('mensajeRecepcion', 'Apoderado llegó con su documentación completa')
        ->call('registrarIngreso')
        ->assertHasNoErrors();

    $entrevista->refresh();
    expect($entrevista->estado)->toBe('ingresada');
    expect($entrevista->lugar)->toBe('BOX 1');

    Notification::assertSentTo(
        $user,
        IngresoApoderado::class,
        function ($notification) use ($entrevista) {
            return $notification->entrevista->id === $entrevista->id &&
                   in_array('mail', $notification->via($entrevista->user));
        }
    );
});

test('interviews with exit recorded move to the bottom of the reception list', function () {
    [$user, $entrevista1] = setupTestEnvironment();

    $entrevista2 = Entrevista::create([
        'school_id' => $entrevista1->school_id,
        'user_id' => $user->id,
        'estudiante_id' => $entrevista1->estudiante_id,
        'fecha' => now('America/Santiago')->format('Y-m-d'),
        'hora' => '10:00:00',
        'motivo' => 'Reunión General',
        'urgencia' => 'normal',
        'estado' => 'ingresada',
        'lugar' => 'BOX 1',
    ]);

    // Mark exit for entrevista1
    $entrevista1->update([
        'hora' => '09:00:00',
        'mensaje_recepcion' => '[SALIDA] El apoderado se retiró del recinto a las 09:30.',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::entrevistas.recepcion');
    $items = $component->get('proximasEntrevistas')->items();

    // entrevista2 (active/pending without exit) must come before entrevista1 (which has exited)
    $ids = array_map(fn ($e) => $e->id, $items);
    $pos1 = array_search($entrevista1->id, $ids);
    $pos2 = array_search($entrevista2->id, $ids);

    expect($pos2)->toBeLessThan($pos1);
});

test('recepcionista can revert entry and return interview to pending state', function () {
    [$user, $entrevista] = setupTestEnvironment();

    $entrevista->update([
        'estado' => 'ingresada',
        'lugar' => 'BOX 2',
        'hora_llegada' => '10:15:00',
        'mensaje_recepcion' => 'Apoderado presente',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::entrevistas.recepcion')
        ->call('revertirIngreso', $entrevista->id);

    $entrevista->refresh();
    expect($entrevista->estado)->toBe('pendiente');
    expect($entrevista->hora_llegada)->toBeNull();
    expect($entrevista->mensaje_recepcion)->toBeNull();
    expect($entrevista->lugar)->toBeNull();
});

test('user with ver-recepcion permission can view reception page but cannot register arrival', function () {
    [$user, $entrevista] = setupTestEnvironment();

    $userReadOnly = User::factory()->create();
    $userReadOnly->update(['current_school_id' => $entrevista->school_id]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($entrevista->school_id);
    $userReadOnly->givePermissionTo([
        Permission::findOrCreate('ver-recepcion', 'web'),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($userReadOnly);

    // Can access route
    $this->get(route('entrevistas.recepcion'))->assertOk();

    // Cannot call registrarIngreso (aborts 403)
    Livewire::test('pages::entrevistas.recepcion')
        ->set('entrevistaSeleccionadaId', $entrevista->id)
        ->set('lugarIngreso', 'BOX 1')
        ->call('registrarIngreso')
        ->assertStatus(403);
});

test('receptionist can delete an attention place', function () {
    [$user, $entrevista] = setupTestEnvironment();

    $lugar = LugarAtencion::create([
        'school_id' => $entrevista->school_id,
        'nombre' => 'LUGAR DUPLICADO',
        'activo' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::entrevistas.recepcion')
        ->call('eliminarLugar', $lugar->id);

    $this->assertDatabaseMissing('lugares_atencion', [
        'id' => $lugar->id,
    ]);
});
