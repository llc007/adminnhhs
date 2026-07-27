<?php

use App\Models\Entrevista;
use App\Models\User;
use App\Notifications\EntrevistaAgendadaApoderado;
use App\Notifications\RespuestaAsistenciaDocenteNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function setupConfirmacionTestEnv()
{
    $docente = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test High School',
        'domain' => 'testhigh.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $docente->update(['current_school_id' => $schoolId]);

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
        'nivel' => 2,
        'modalidad' => 'media',
        'letra' => 'B',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = DB::table('estudiantes')->insertGetId([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'Carlos Perez',
        'rut_numero' => '98765432',
        'rut_dv' => '1',
        'apoderado_nombres' => 'Juan',
        'apoderado_apellido_pat' => 'Perez',
        'apoderado_email' => 'apoderado.perez@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $entrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'estudiante_id' => $studentId,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '11:00:00',
        'motivo' => 'Rendimiento Académico',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
        'lugar' => 'SALA DE REUNIONES',
    ]);

    return [$docente, $entrevista];
}

test('entrevista automatically generates a confirmation token on creation', function () {
    [$docente, $entrevista] = setupConfirmacionTestEnv();

    expect($entrevista->confirmacion_token)->not->toBeEmpty();
    expect(strlen($entrevista->confirmacion_token))->toBe(40);
    expect($entrevista->estado_asistencia)->toBe('pendiente');
});

test('parent can confirm attendance via public confirmation component and notify docente', function () {
    Notification::fake();
    [$docente, $entrevista] = setupConfirmacionTestEnv();

    Livewire::test('pages::entrevistas.confirmacion_publica', ['token' => $entrevista->confirmacion_token])
        ->set('emailRespuesta', 'apoderado.perez@example.com')
        ->call('confirmarAsistencia')
        ->assertHasNoErrors();

    $entrevista->refresh();
    expect($entrevista->estado_asistencia)->toBe('confirmada');
    expect($entrevista->confirmado_desde_email)->toBe('apoderado.perez@example.com');
    expect($entrevista->confirmado_at)->not->toBeNull();

    Notification::assertSentTo(
        $docente,
        RespuestaAsistenciaDocenteNotification::class,
        function ($notification) use ($entrevista) {
            return $notification->entrevista->id === $entrevista->id &&
                   $notification->entrevista->estado_asistencia === 'confirmada';
        }
    );
});

test('parent can reject attendance with reason via public component and notify docente', function () {
    Notification::fake();
    [$docente, $entrevista] = setupConfirmacionTestEnv();

    Livewire::test('pages::entrevistas.confirmacion_publica', ['token' => $entrevista->confirmacion_token])
        ->set('emailRespuesta', 'apoderado.perez@example.com')
        ->set('motivoRechazo', 'Tengo turno laboral médico a esa misma hora, solicito reagendar.')
        ->call('rechazarAsistencia')
        ->assertHasNoErrors();

    $entrevista->refresh();
    expect($entrevista->estado_asistencia)->toBe('rechazada');
    expect($entrevista->confirmado_desde_email)->toBe('apoderado.perez@example.com');
    expect($entrevista->motivo_rechazo_asistencia)->toContain('turno laboral');

    Notification::assertSentTo(
        $docente,
        RespuestaAsistenciaDocenteNotification::class,
        function ($notification) use ($entrevista) {
            return $notification->entrevista->id === $entrevista->id &&
                   $notification->entrevista->estado_asistencia === 'rechazada';
        }
    );
});

test('docente can resend confirmation email to custom address from bitacora', function () {
    Notification::fake();
    [$docente, $entrevista] = setupConfirmacionTestEnv();

    Livewire::actingAs($docente)
        ->test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->set('emailReenvioConfirmacion', 'nuevo.apoderado@example.com')
        ->call('reenviarConfirmacionAsistencia')
        ->assertHasNoErrors();

    $entrevista->refresh();
    expect($entrevista->correo_citacion_enviado)->toBe('nuevo.apoderado@example.com');

    Notification::assertSentOnDemand(
        EntrevistaAgendadaApoderado::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'nuevo.apoderado@example.com';
        }
    );
});
