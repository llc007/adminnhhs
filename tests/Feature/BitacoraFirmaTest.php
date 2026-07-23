<?php

use App\Models\Bitacora;
use App\Models\Entrevista;
use App\Models\Estudiante;
use App\Models\MailLog;
use App\Models\User;
use App\Notifications\BitacoraResumenNotification;
use App\Notifications\BitacoraSolicitudFirmaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function setupBitacoraTest()
{
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
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
        'nivel' => 1,
        'modalidad' => 'media',
        'letra' => 'A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $docente = User::factory()->create(['current_school_id' => $schoolId]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

    $estudiante = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'CARLOS PEREZ',
        'apoderado_nombres' => 'JUAN PEREZ',
        'apoderado_rut_numero' => '12345678',
        'apoderado_rut_dv' => '9',
        'apoderado_email' => 'apoderado@test.com',
    ]);

    $entrevista = Entrevista::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'user_id' => $docente->id,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '10:00',
        'motivo' => 'Rendimiento académico',
        'estado' => 'pendiente',
    ]);

    $bitacora = Bitacora::create([
        'entrevista_id' => $entrevista->id,
        'resumen' => 'Se conversó sobre las notas del alumno.',
        'observaciones' => 'Nota confidencial interna.',
        'acuerdos' => [['titulo' => 'Refuerzo', 'descripcion' => 'Estudiar 1h diaria']],
        'estado_formulario' => 'borrador',
    ]);

    return [$docente, $entrevista, $bitacora, $estudiante];
}

test('can record presencial signature with custom attendee name and rut', function () {
    [$docente, $entrevista, $bitacora] = setupBitacoraTest();
    $this->actingAs($docente);

    Livewire::test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->call('abrirModalFirmaPresencial')
        ->set('firmanteNombre', 'MARIA TAPIA (TIA)')
        ->set('firmanteRutNumero', '98765432')
        ->set('firmanteRutDv', '1')
        ->set('firmaSvg', 'data:image/svg+xml;base64,sample')
        ->call('guardarFirmaPresencial');

    $bitacora->refresh();
    expect($bitacora->estado_firma)->toBe('firmada_presencial');
    expect($bitacora->firmante_nombre)->toBe('MARIA TAPIA (TIA)');
    expect($bitacora->firmante_rut)->toBe('98765432-1');
});

test('can send online signature request email', function () {
    Notification::fake();
    [$docente, $entrevista, $bitacora] = setupBitacoraTest();
    $this->actingAs($docente);

    Livewire::test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->call('abrirModalFirmaOnline')
        ->set('firmanteEmail', 'otro.apoderado@test.com')
        ->call('enviarFirmaOnline');

    $bitacora->refresh();
    expect($bitacora->firma_token)->not->toBeNull();

    Notification::assertSentOnDemand(BitacoraSolicitudFirmaNotification::class);
});

test('can sign online via public route', function () {
    [$docente, $entrevista, $bitacora] = setupBitacoraTest();
    $token = 'test-token-12345';
    $bitacora->update([
        'firma_token' => $token,
        'firma_token_expires_at' => now()->addDays(7),
    ]);

    Livewire::test('pages::entrevistas.firma_publica', ['token' => $token])
        ->set('firmanteNombre', 'PEDRO PEREZ')
        ->set('firmanteRutNumero', '11222333')
        ->set('firmanteRutDv', 'K')
        ->call('firmar');

    $bitacora->refresh();
    expect($bitacora->estado_firma)->toBe('firmada_online');
    expect($bitacora->firmante_nombre)->toBe('PEDRO PEREZ');
    expect($bitacora->firmante_rut)->toBe('11222333-K');
});

test('can send interview summary email excluding internal observaciones', function () {
    Notification::fake();
    [$docente, $entrevista, $bitacora] = setupBitacoraTest();
    $this->actingAs($docente);

    Livewire::test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->call('abrirModalEnviarResumen')
        ->set('enviarApoderado', true)
        ->set('emailApoderado', 'apoderado@test.com')
        ->call('enviarResumenCorreos');

    Notification::assertSentOnDemand(BitacoraResumenNotification::class, function ($notification) {
        $mail = $notification->toMail(new stdClass);
        $rendered = implode("\n", $mail->introLines);

        expect($rendered)->toContain('Se conversó sobre las notas del alumno.');
        expect($rendered)->not->toContain('Nota confidencial interna.');

        return true;
    });
});

test('auto saves pending acuerdo typed in inputs when finalizing interview', function () {
    [$docente, $entrevista, $bitacora] = setupBitacoraTest();
    $this->actingAs($docente);

    Livewire::test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->set('nuevoAcuerdoTitulo', 'Compromiso de Asistencia')
        ->set('nuevoAcuerdoDesc', 'Asistir a reforzamiento los martes')
        ->call('enviarResumenCorreos');

    $bitacora->refresh();
    $acuerdos = $bitacora->acuerdos;
    expect(count($acuerdos))->toBe(2);
    expect($acuerdos[1]['titulo'])->toBe('Compromiso de Asistencia');
    expect($acuerdos[1]['descripcion'])->toBe('Asistir a reforzamiento los martes');
});

test('creates not_sent mail log when email module is disabled and allows admin resend', function () {
    [$docente, $entrevista, $bitacora] = setupBitacoraTest();
    $school = $docente->currentSchool;

    // Disable email sending module
    $modulos = $school->modulos_publicados;
    $modulos['envio_correos'] = false;
    $school->modulos_publicados = $modulos;
    $school->save();

    $this->actingAs($docente);

    Livewire::test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->set('enviarApoderado', true)
        ->set('emailApoderado', 'bloqueado@test.com')
        ->call('enviarResumenCorreos');

    $log = MailLog::where('to', 'bloqueado@test.com')->first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('not_sent');

    // Admin can resend mail from MailLogs component
    $admin = User::factory()->create(['current_school_id' => $school->id]);
    $admin->syncRolesForSchool($school->id, ['administrador']);
    $this->actingAs($admin);

    Mail::fake();

    Livewire::test('pages::admin.mail_logs')
        ->call('reenviarCorreo', $log->id);

    $log->refresh();
    expect($log->status)->toBe('sent');
});
