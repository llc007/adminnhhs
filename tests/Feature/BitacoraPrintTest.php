<?php

use App\Models\Bitacora;
use App\Models\Entrevista;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function setupBitacoraPrintTest(): array
{
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Liceo San Juan',
        'domain' => 'sanjuan.cl',
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
        'nivel' => 2,
        'modalidad' => 'media',
        'letra' => 'B',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $docente = User::factory()->create([
        'nombres' => 'ROBERTO',
        'apellido_pat' => 'GOMEZ',
        'current_school_id' => $schoolId,
    ]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

    $estudiante = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'MATIAS FERNANDEZ',
        'rut_numero' => '21234567',
        'rut_dv' => '8',
        'apoderado_nombres' => 'LUCIA',
        'apoderado_apellido_pat' => 'FERNANDEZ',
        'apoderado_rut_numero' => '13456789',
        'apoderado_rut_dv' => '0',
        'apoderado_email' => 'lucia@test.com',
        'apoderado_telefono' => '+56911223344',
        'apoderado_parentesco' => 'Madre',
    ]);

    $entrevista = Entrevista::create([
        'school_id' => $schoolId,
        'estudiante_id' => $estudiante->id,
        'user_id' => $docente->id,
        'fecha' => '2026-11-15',
        'hora' => '11:30',
        'motivo' => 'Rendimiento académico y conducta en aula',
        'estado' => 'realizada',
        'lugar' => 'Sala de Entrevistas N° 2',
    ]);

    $bitacora = Bitacora::create([
        'entrevista_id' => $entrevista->id,
        'resumen' => '<p>Se abordaron las calificaciones del primer trimestre y compromisos de estudio.</p>',
        'observaciones' => 'El apoderado muestra buena disposición al apoyo en el hogar.',
        'acuerdos' => [
            ['titulo' => 'Horario de Estudio', 'descripcion' => 'Establecer 2 horas diarias de repaso sin pantallas.'],
            ['titulo' => 'Reforzamiento Matemáticas', 'descripcion' => 'Asistir a talleres los días jueves.'],
        ],
        'estado_formulario' => 'finalizado',
        'estado_firma' => 'firmada_presencial',
        'firmante_nombre' => 'LUCIA FERNANDEZ',
        'firmante_rut' => '13456789-0',
        'firma_svg' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
        'firmado_at' => now('America/Santiago'),
    ]);

    return [$docente, $entrevista, $bitacora, $estudiante, $schoolId];
}

test('button to print bitacora pdf is visible beside entrevista finalizada when interview is realizada', function () {
    [$docente, $entrevista] = setupBitacoraPrintTest();

    $this->actingAs($docente);

    Livewire::test('pages::entrevistas.bitacora', ['entrevista' => $entrevista])
        ->assertSee('Entrevista Finalizada')
        ->assertSee('Imprimir PDF')
        ->assertSee(route('entrevistas.imprimir_bitacora', $entrevista->id));
});

test('authorized teacher can view printable bitacora page with all student, agreement, and signature details', function () {
    [$docente, $entrevista] = setupBitacoraPrintTest();

    $this->actingAs($docente);

    $response = $this->get(route('entrevistas.imprimir_bitacora', $entrevista->id));

    $response->assertOk()
        ->assertSee('Acta Oficial de Entrevista y Bitácora Institucional')
        ->assertDontSee('Emisión:')
        ->assertDontSee('Sistema de Gestión y Acompañamiento')
        ->assertSee('MATIAS FERNANDEZ')
        ->assertSee('LUCIA FERNANDEZ')
        ->assertSee('ROBERTO GOMEZ')
        ->assertSee('Horario de Estudio')
        ->assertSee('Reforzamiento Matemáticas')
        ->assertSee('El apoderado muestra buena disposición')
        ->assertSee('Acta firmada digitalmente')
        ->assertSee('window.print()', false)
        ->assertDontSee('window.addEventListener(\'load\'', false);
});

test('shows sin firma when apoderado has not signed', function () {
    [$docente, $entrevista, $bitacora] = setupBitacoraPrintTest();
    $bitacora->update([
        'estado_firma' => 'pendiente',
        'firma_svg' => null,
        'firmante_nombre' => null,
        'firmante_rut' => null,
    ]);

    $this->actingAs($docente);

    $response = $this->get(route('entrevistas.imprimir_bitacora', $entrevista->id));

    $response->assertOk()
        ->assertSee('Sin firma')
        ->assertDontSee('Acta firmada digitalmente');
});

test('superadmin can access printable bitacora report', function () {
    [, $entrevista, , , $schoolId] = setupBitacoraPrintTest();

    $superadmin = User::factory()->create(['current_school_id' => $schoolId]);
    $superadmin->syncRolesForSchool($schoolId, ['superadmin']);

    $this->actingAs($superadmin);

    $this->get(route('entrevistas.imprimir_bitacora', $entrevista->id))
        ->assertOk();
});

test('unauthorized teacher cannot access another teachers bitacora print page', function () {
    [, $entrevista, , , $schoolId] = setupBitacoraPrintTest();

    $otroDocente = User::factory()->create(['current_school_id' => $schoolId]);
    $otroDocente->syncRolesForSchool($schoolId, ['docente']);

    $this->actingAs($otroDocente);

    $this->get(route('entrevistas.imprimir_bitacora', $entrevista->id))
        ->assertForbidden();
});

test('unauthenticated guest is redirected to login when trying to access printable bitacora', function () {
    [, $entrevista] = setupBitacoraPrintTest();

    $this->get(route('entrevistas.imprimir_bitacora', $entrevista->id))
        ->assertRedirect(route('login'));
});
