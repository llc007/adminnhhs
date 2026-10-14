<?php

use App\Models\Atraso;
use App\Models\Bitacora;
use App\Models\Curso;
use App\Models\Entrevista;
use App\Models\Estudiante;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function setupPortalApoderadoEnvironment(): array
{
    $school = School::create([
        'name' => 'Colegio Test Portal',
        'domain' => 'testportal.cl',
    ]);

    $schoolId = $school->id;

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => '2026',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $profesorJefe = User::factory()->create([
        'nombres' => 'Profesor',
        'apellido_pat' => 'Jefe',
        'email' => 'profesorjefe@testportal.cl',
        'current_school_id' => $schoolId,
    ]);

    $curso = Curso::create([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'nivel' => 1,
        'modalidad' => 'media',
        'letra' => 'A',
        'nombre_fc' => '1° Medio A',
        'jefe_id' => $profesorJefe->id,
    ]);

    // Crear usuario estudiante
    $userEstudiante = User::factory()->create([
        'nombres' => 'Juan',
        'apellido_pat' => 'Perez',
        'apellido_mat' => null,
        'email' => 'juan.perez@testportal.cl',
        'current_school_id' => $schoolId,
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    Role::findOrCreate('estudiante', 'web');
    $userEstudiante->syncRolesForSchool($schoolId, ['estudiante']);

    // Crear ficha estudiante
    $estudiante = Estudiante::create([
        'user_id' => $userEstudiante->id,
        'school_id' => $schoolId,
        'curso_id' => $curso->id,
        'nombres_csv' => 'JUAN PEREZ GONZALEZ',
        'rut_numero' => '22111333',
        'rut_dv' => '4',
        'email' => 'juan.perez@testportal.cl',
        'estado' => 'activo',
        'apoderado_nombres' => 'MARIA',
        'apoderado_apellido_pat' => 'GONZALEZ',
        'apoderado_rut_numero' => '15222333',
        'apoderado_rut_dv' => '8',
        'apoderado_email' => 'apoderado@test.cl',
        'apoderado_parentesco' => 'Madre',
    ]);

    return compact('school', 'curso', 'profesorJefe', 'userEstudiante', 'estudiante');
}

test('invitados son redirigidos al login al intentar entrar al portal', function () {
    $this->get(route('portal-apoderado'))
        ->assertRedirect(route('login'));
});

test('estudiante puede acceder correctamente a su portal', function () {
    $env = setupPortalApoderadoEnvironment();

    $this->actingAs($env['userEstudiante'])
        ->get(route('portal-apoderado'))
        ->assertOk()
        ->assertSee('Portal de Apoderados')
        ->assertSee($env['estudiante']->nombreCompleto())
        ->assertSee('1° Medio A');
});

test('estudiante ve sus atrasos y metricas en el portal', function () {
    $env = setupPortalApoderadoEnvironment();
    $estudiante = $env['estudiante'];

    // Registrar 2 atrasos
    Atraso::create([
        'school_id' => $env['school']->id,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $env['curso']->id,
        'fecha' => now()->toDateString(),
        'hora' => '08:15:00',
        'minutos_atraso' => 15,
        'jornada' => 'manana',
        'estado' => 'injustificado',
        'motivo' => 'Problemas de locomoción',
    ]);

    Atraso::create([
        'school_id' => $env['school']->id,
        'estudiante_id' => $estudiante->id,
        'curso_id' => $env['curso']->id,
        'fecha' => now()->subDay()->toDateString(),
        'hora' => '08:10:00',
        'minutos_atraso' => 10,
        'jornada' => 'manana',
        'estado' => 'justificado',
        'motivo' => 'Hora médica',
    ]);

    Livewire::actingAs($env['userEstudiante'])
        ->test('pages::apoderados.portal')
        ->assertSee('Problemas de locomoción')
        ->assertSee('Hora médica')
        ->assertSee('Injustificado')
        ->assertSee('Justificado');
});

test('portal muestra entrevistas generales y oculta notas confidenciales', function () {
    $env = setupPortalApoderadoEnvironment();
    $estudiante = $env['estudiante'];

    // Entrevista pública/general
    $entrevistaGeneral = Entrevista::create([
        'school_id' => $env['school']->id,
        'estudiante_id' => $estudiante->id,
        'user_id' => $env['profesorJefe']->id,
        'fecha' => now()->addDays(2)->toDateString(),
        'hora' => '10:00:00',
        'motivo' => 'Rendimiento académico primer trimestre',
        'estado' => 'pendiente',
        'es_confidencial' => false,
    ]);

    Bitacora::create([
        'entrevista_id' => $entrevistaGeneral->id,
        'resumen' => 'Texto privado de la bitacora que no debe verse',
        'estado_firma' => 'firmada_presencial',
    ]);

    // Entrevista confidencial (ej. equipo psicosocial)
    Entrevista::create([
        'school_id' => $env['school']->id,
        'estudiante_id' => $estudiante->id,
        'user_id' => $env['profesorJefe']->id,
        'fecha' => now()->toDateString(),
        'hora' => '11:00:00',
        'motivo' => 'Tema psicosocial confidencial',
        'estado' => 'realizada',
        'es_confidencial' => true,
    ]);

    Livewire::actingAs($env['userEstudiante'])
        ->test('pages::apoderados.portal')
        ->set('tab', 'entrevistas')
        ->assertSee('Rendimiento académico primer trimestre')
        ->assertSee('Firmada')
        ->assertDontSee('Texto privado de la bitacora que no debe verse')
        ->assertDontSee('Tema psicosocial confidencial');
});

test('apoderado con multiples estudiantes puede alternar entre ellos', function () {
    $env = setupPortalApoderadoEnvironment();
    $estudiante1 = $env['estudiante'];

    // Crear segundo estudiante (hermano) con el mismo apoderado_rut_numero
    $estudiante2 = Estudiante::create([
        'school_id' => $env['school']->id,
        'curso_id' => $env['curso']->id,
        'nombres_csv' => 'SOFIA PEREZ GONZALEZ',
        'rut_numero' => '23111444',
        'rut_dv' => '5',
        'email' => 'sofia.perez@testportal.cl',
        'estado' => 'activo',
        'apoderado_nombres' => 'MARIA',
        'apoderado_apellido_pat' => 'GONZALEZ',
        'apoderado_rut_numero' => '15222333',
        'apoderado_rut_dv' => '8',
    ]);

    Livewire::actingAs($env['userEstudiante'])
        ->test('pages::apoderados.portal')
        ->assertSee($estudiante1->nombreCompleto())
        ->assertSee($estudiante2->nombreCompleto())
        ->assertSee('2 Estudiantes Vinculados')
        ->call('seleccionarEstudiante', $estudiante2->id)
        ->assertSet('selectedEstudianteId', $estudiante2->id);
});

test('estudiante es redirigido a su portal si intenta acceder al dashboard general', function () {
    $env = setupPortalApoderadoEnvironment();

    $this->actingAs($env['userEstudiante'])
        ->get('/dashboard')
        ->assertRedirect(route('portal-apoderado'));
});
