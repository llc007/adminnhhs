<?php

use App\Models\Entrevista;
use App\Models\User;
use App\Notifications\EntrevistaCancelada;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

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

    $user->schools()->attach($schoolId, ['roles' => json_encode(['administrador', 'docente'])]);

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
    $user->schools()->attach($schoolId, ['roles' => json_encode(['recepcion'])]);

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
