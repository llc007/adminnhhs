<?php

use App\Enums\Modalidad;
use App\Models\AcademicYear;
use App\Models\Curso;
use App\Models\EvaluacionDocente;
use App\Models\School;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function createSchoolAndStaff(): array
{
    $school = School::create(['name' => 'Colegio Test', 'domain' => 'test.cl']);
    $encargada = User::factory()->create(['current_school_id' => $school->id]);
    $encargada->syncRolesForSchool($school->id, ['docente', 'comision_ed']);

    $profesor = User::factory()->create([
        'nombres' => 'CARLOS ALBERTO',
        'apellido_pat' => 'SOTO',
        'current_school_id' => $school->id,
    ]);
    $profesor->syncRolesForSchool($school->id, ['docente']);

    return [$school, $encargada, $profesor];
}

test('kiosk route is publicly accessible without login for tablets and requires PIN', function () {
    [$school] = createSchoolAndStaff();

    // Sin iniciar sesión, la tablet puede abrir la ruta por QR
    $this->get(route('encuestas.kiosco', ['schoolId' => $school->id]))
        ->assertOk()
        ->assertSee('PIN de la Encuesta');

    // Un PIN incorrecto no desbloquea la encuesta
    Livewire::test('pages::encuestas.kiosco', ['schoolId' => $school->id])
        ->set('pinIngresado', '9999')
        ->call('validarPin')
        ->assertSet('pinValidado', false)
        ->assertSee('PIN incorrecto');
});

test('only users with comision_ed or superadmin role can access results and QR print page', function () {
    [$school, $encargada, $profesor] = createSchoolAndStaff();

    // Un docente sin rol comision_ed no puede ver resultados ni imprimir QR
    $this->actingAs($profesor)
        ->get(route('encuestas.resultados'))
        ->assertForbidden();

    $this->actingAs($profesor)
        ->get(route('encuestas.imprimir_qr'))
        ->assertForbidden();

    // Un estudiante tampoco puede entrar
    $estudiante = User::factory()->create(['current_school_id' => $school->id]);
    $estudiante->syncRolesForSchool($school->id, ['estudiante']);

    $this->actingAs($estudiante)
        ->get(route('encuestas.resultados'))
        ->assertForbidden();

    // La encargada con rol comision_ed sí puede acceder a resultados e imprimir QR
    $this->actingAs($encargada)
        ->get(route('encuestas.resultados'))
        ->assertOk();

    $this->actingAs($encargada)
        ->get(route('encuestas.imprimir_qr'))
        ->assertOk()
        ->assertSee('<svg', false);
});

test('administrator can assign comision_ed role from funcionario digital ficha', function () {
    [$school, , $profesor] = createSchoolAndStaff();

    $admin = User::factory()->create(['current_school_id' => $school->id]);
    $admin->syncRolesForSchool($school->id, ['administrador', 'superadmin']);

    $this->actingAs($admin);

    Livewire::test('pages::usuarios.funcionarios.ficha', ['id' => $profesor->id])
        ->assertSee('Comisión ED')
        ->set('roles', ['docente', 'comision_ed'])
        ->call('guardar')
        ->assertHasNoErrors();

    expect($profesor->fresh()->hasRole('comision_ed'))->toBeTrue();
});

test('anonymous tablet user can unlock with student PIN and submit 10-question survey', function () {
    [$school, , $profesor] = createSchoolAndStaff();

    // Sin actingAs() (100% sin sesión de usuario, como en una tablet)
    $component = Livewire::test('pages::encuestas.kiosco', ['schoolId' => $school->id])
        ->set('pinIngresado', '1234')
        ->call('validarPin')
        ->assertSet('pinValidado', true)
        ->assertSet('tipoRespondente', 'estudiante')
        ->assertSet('paso', 'seleccion')
        ->call('seleccionarProfesor', $profesor->id)
        ->assertSet('profesorId', $profesor->id)
        ->call('comenzarEncuesta')
        ->assertSet('paso', 'encuesta')
        ->assertSee('CARLOS ALBERTO SOTO');

    // Responder 10 preguntas con puntaje 3 (Siempre) salvo la 10 con puntaje 2 (Casi siempre)
    for ($i = 1; $i <= 9; $i++) {
        $component->call('responderPregunta', $i, 3);
    }
    $component->call('responderPregunta', 10, 2);

    $component->call('enviarEncuesta')
        ->assertSet('paso', 'completado');

    $eval = EvaluacionDocente::first();
    expect($eval)->not->toBeNull()
        ->and($eval->profesor_id)->toBe($profesor->id)
        ->and($eval->tipo_respondente)->toBe('estudiante')
        ->and($eval->puntaje_obtenido)->toBe(29)
        ->and($eval->puntaje_maximo)->toBe(30)
        ->and($eval->promedio)->toBe(2.9)
        ->and($eval->registrado_por_id)->toBeNull();
});

test('anonymous tablet user can unlock with apoderado PIN and auto-detects profesor jefe 7 questions', function () {
    [$school, , $profesor] = createSchoolAndStaff();

    $year = AcademicYear::create([
        'school_id' => $school->id,
        'name' => '2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);
    $curso = Curso::create([
        'school_id' => $school->id,
        'academic_year_id' => $year->id,
        'modalidad' => Modalidad::Basica,
        'nivel' => 5,
        'letra' => 'A',
    ]);
    $curso->jefe_id = $profesor->id;
    $curso->save();

    $component = Livewire::test('pages::encuestas.kiosco', ['schoolId' => $school->id])
        ->set('pinIngresado', '5678')
        ->call('validarPin')
        ->assertSet('pinValidado', true)
        ->assertSet('tipoRespondente', 'apoderado')
        ->call('seleccionarProfesor', $profesor->id)
        ->assertSet('conJefatura', true)
        ->call('comenzarEncuesta')
        ->assertSee('delegado pastoral'); // Pregunta 7

    for ($i = 1; $i <= 7; $i++) {
        $component->call('responderPregunta', $i, 3);
    }

    $component->call('enviarEncuesta')
        ->assertSet('paso', 'completado');

    $eval = EvaluacionDocente::first();
    expect($eval)->not->toBeNull()
        ->and($eval->tipo_respondente)->toBe('apoderado')
        ->and($eval->con_jefatura)->toBeTrue()
        ->and($eval->puntaje_obtenido)->toBe(21)
        ->and($eval->puntaje_maximo)->toBe(21)
        ->and($eval->promedio)->toBe(3.0);
});

test('superadmin can modify PINs and disable survey completion', function () {
    [$school] = createSchoolAndStaff();

    $superadmin = User::factory()->create(['current_school_id' => $school->id]);
    $superadmin->syncRolesForSchool($school->id, ['superadmin']);

    $this->actingAs($superadmin);

    Livewire::test('pages::encuestas.resultados')
        ->set('pinEstudiante', '2468')
        ->set('pinApoderado', '1357')
        ->set('encuestaHabilitada', false)
        ->call('guardarConfiguracionEncuesta')
        ->assertHasNoErrors();

    $school->refresh();
    expect($school->encuestasHabilitadas())->toBeFalse()
        ->and($school->pinEncuestaEstudiante())->toBe('2468')
        ->and($school->pinEncuestaApoderado())->toBe('1357');

    // Al estar deshabilitada, la tablet ve el mensaje de Periodo Cerrado
    Livewire::test('pages::encuestas.kiosco', ['schoolId' => $school->id])
        ->assertSee('Periodo de Encuestas Cerrado');
});

test('results page shows 20 quota for students and 2 quota for apoderados', function () {
    [$school, $encargada, $profesor] = createSchoolAndStaff();

    EvaluacionDocente::create([
        'school_id' => $school->id,
        'profesor_id' => $profesor->id,
        'tipo_respondente' => 'estudiante',
        'con_jefatura' => false,
        'respuestas' => ['1' => 3, '2' => 3, '3' => 3, '4' => 3, '5' => 3, '6' => 3, '7' => 3, '8' => 3, '9' => 3, '10' => 3],
        'puntaje_obtenido' => 30,
        'puntaje_maximo' => 30,
        'promedio' => 3.00,
    ]);

    EvaluacionDocente::create([
        'school_id' => $school->id,
        'profesor_id' => $profesor->id,
        'tipo_respondente' => 'apoderado',
        'con_jefatura' => false,
        'respuestas' => ['1' => 3, '2' => 3, '3' => 3, '4' => 3, '5' => 3, '6' => 3],
        'puntaje_obtenido' => 18,
        'puntaje_maximo' => 18,
        'promedio' => 3.00,
    ]);

    $this->actingAs($encargada);

    Livewire::test('pages::encuestas.resultados')
        ->assertSee('CARLOS ALBERTO SOTO')
        ->assertSee('1 / 20')
        ->call('cambiarVista', 'apoderado')
        ->assertSee('1 / 2');
});

test('comision_ed or superadmin can reset and delete all surveys', function () {
    [$school, $encargada, $profesor] = createSchoolAndStaff();

    EvaluacionDocente::factory()->count(3)->create([
        'school_id' => $school->id,
        'profesor_id' => $profesor->id,
        'tipo_respondente' => 'estudiante',
    ]);

    EvaluacionDocente::factory()->apoderado()->count(2)->create([
        'school_id' => $school->id,
        'profesor_id' => $profesor->id,
    ]);

    expect(EvaluacionDocente::where('school_id', $school->id)->count())->toBe(5);

    $this->actingAs($encargada);

    Livewire::test('pages::encuestas.resultados')
        ->set('tipoReset', 'todas')
        ->call('reiniciarEncuestas')
        ->assertSet('modalConfirmarReset', false);

    expect(EvaluacionDocente::where('school_id', $school->id)->count())->toBe(0);
});

test('only users with docente role appear in kiosk selector and results table', function () {
    [$school, $encargada, $profesor] = createSchoolAndStaff();

    $inspector = User::factory()->create([
        'nombres' => 'PEDRO INSPECTOR',
        'apellido_pat' => 'GOMEZ',
        'current_school_id' => $school->id,
    ]);
    $inspector->syncRolesForSchool($school->id, ['inspector']);

    // En la tablet (kiosco) solo aparece el docente, no el inspector
    Livewire::test('pages::encuestas.kiosco', ['schoolId' => $school->id])
        ->set('pinIngresado', '1234')
        ->call('validarPin')
        ->assertSee('CARLOS ALBERTO SOTO')
        ->assertDontSee('PEDRO INSPECTOR GOMEZ');

    // En la tabla de resultados solo aparece el docente, no el inspector
    $this->actingAs($encargada);
    Livewire::test('pages::encuestas.resultados')
        ->assertSee('CARLOS ALBERTO SOTO')
        ->assertDontSee('PEDRO INSPECTOR GOMEZ');
});

test('unauthenticated guest scanning QR can see and search professors even with clean spatie team context', function () {
    [$school, $encargada, $profesor] = createSchoolAndStaff();

    // Ensure user is guest and Spatie team context was reset
    auth()->logout();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    Livewire::test('pages::encuestas.kiosco', ['schoolId' => $school->id])
        ->set('pinIngresado', '1234')
        ->call('validarPin')
        ->assertSet('paso', 'seleccion')
        ->assertSee('CARLOS ALBERTO SOTO')
        ->set('busquedaProfesor', 'Soto')
        ->assertSee('CARLOS ALBERTO SOTO')
        ->set('busquedaProfesor', 'InexistenteXYZ')
        ->assertSee('No se encontraron profesores que coincidan con')
        ->assertSee('InexistenteXYZ');
});

test('evaluacion docente classification calculates accurate percentages and labels according to the scale', function () {
    // Escala:
    // Destacado: 90% o más
    // Competente: 80% a 89.9%
    // Básico: 70% a 79.9%
    // Por lograr: menos de 70%

    // Estudiante (max 30 pts)
    expect(EvaluacionDocente::clasificacionPorcentual(30.0, 30))->toMatchArray([
        'nombre' => 'Destacado',
        'porcentaje' => 100.0,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(27.0, 30))->toMatchArray([
        'nombre' => 'Destacado',
        'porcentaje' => 90.0,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(24.0, 30))->toMatchArray([
        'nombre' => 'Competente',
        'porcentaje' => 80.0,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(21.0, 30))->toMatchArray([
        'nombre' => 'Básico',
        'porcentaje' => 70.0,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(20.0, 30))->toMatchArray([
        'nombre' => 'Por lograr',
        'porcentaje' => 66.7,
    ]);

    // Apoderado con jefatura (max 21 pts)
    expect(EvaluacionDocente::clasificacionPorcentual(21.0, 21))->toMatchArray([
        'nombre' => 'Destacado',
        'porcentaje' => 100.0,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(18.9, 21))->toMatchArray([
        'nombre' => 'Destacado',
        'porcentaje' => 90.0,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(17.0, 21))->toMatchArray([
        'nombre' => 'Competente',
        'porcentaje' => 81.0,
    ]);

    // Apoderado sin jefatura (max 18 pts)
    expect(EvaluacionDocente::clasificacionPorcentual(18.0, 18))->toMatchArray([
        'nombre' => 'Destacado',
        'porcentaje' => 100.0,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(15.0, 18))->toMatchArray([
        'nombre' => 'Competente',
        'porcentaje' => 83.3,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(13.0, 18))->toMatchArray([
        'nombre' => 'Básico',
        'porcentaje' => 72.2,
    ]);
    expect(EvaluacionDocente::clasificacionPorcentual(11.0, 18))->toMatchArray([
        'nombre' => 'Por lograr',
        'porcentaje' => 61.1,
    ]);

    expect(EvaluacionDocente::clasificacionPorcentual(null, 30))->toBeNull();
});

test('results table displays classification column and sorts teachers by course: basica then media then subject teachers', function () {
    [$school, $encargada] = createSchoolAndStaff();

    $year = AcademicYear::create([
        'school_id' => $school->id,
        'name' => '2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    // 1. Docente Media (2° Medio A)
    $profMedia = User::factory()->create([
        'nombres' => 'MARIA',
        'apellido_pat' => 'MEDIA',
        'current_school_id' => $school->id,
    ]);
    $profMedia->syncRolesForSchool($school->id, ['docente']);
    $cursoMedia = Curso::create([
        'school_id' => $school->id,
        'academic_year_id' => $year->id,
        'modalidad' => Modalidad::Media,
        'nivel' => 2,
        'letra' => 'A',
    ]);
    $cursoMedia->jefe_id = $profMedia->id;
    $cursoMedia->save();

    // 2. Docente Básica (3° Básico B)
    $profBasica = User::factory()->create([
        'nombres' => 'JUAN',
        'apellido_pat' => 'BASICA',
        'current_school_id' => $school->id,
    ]);
    $profBasica->syncRolesForSchool($school->id, ['docente']);
    $cursoBasica = Curso::create([
        'school_id' => $school->id,
        'academic_year_id' => $year->id,
        'modalidad' => Modalidad::Basica,
        'nivel' => 3,
        'letra' => 'B',
    ]);
    $cursoBasica->jefe_id = $profBasica->id;
    $cursoBasica->save();

    // 3. Docente sin jefatura (Docente de Asignatura)
    $profAsignatura = User::factory()->create([
        'nombres' => 'ALEX',
        'apellido_pat' => 'ASIGNATURA',
        'current_school_id' => $school->id,
    ]);
    $profAsignatura->syncRolesForSchool($school->id, ['docente']);

    // Crear evaluaciones para comprobar la clasificación:
    // profBasica obtiene 28/30 (93.3% -> Destacado)
    EvaluacionDocente::create([
        'school_id' => $school->id,
        'profesor_id' => $profBasica->id,
        'tipo_respondente' => 'estudiante',
        'con_jefatura' => true,
        'respuestas' => ['1' => 3, '2' => 3, '3' => 3, '4' => 3, '5' => 3, '6' => 3, '7' => 3, '8' => 3, '9' => 2, '10' => 2],
        'puntaje_obtenido' => 28,
        'puntaje_maximo' => 30,
        'promedio' => 2.8,
    ]);

    // profMedia obtiene 25/30 (83.3% -> Competente)
    EvaluacionDocente::create([
        'school_id' => $school->id,
        'profesor_id' => $profMedia->id,
        'tipo_respondente' => 'estudiante',
        'con_jefatura' => true,
        'respuestas' => ['1' => 3, '2' => 3, '3' => 3, '4' => 2, '5' => 2, '6' => 2, '7' => 3, '8' => 3, '9' => 2, '10' => 2],
        'puntaje_obtenido' => 25,
        'puntaje_maximo' => 30,
        'promedio' => 2.5,
    ]);

    $this->actingAs($encargada);

    $component = Livewire::test('pages::encuestas.resultados')
        ->assertSee('Clasificación')
        ->assertSee('Destacado')
        ->assertSee('93.3%')
        ->assertSee('Competente')
        ->assertSee('83.3%');

    // Verificar el orden de las filas en tablaResultados:
    // 1º Básica (3° Básico B), 2º Media (2° Medio A), 3º Asignatura
    $tabla = collect($component->get('tablaResultados'));
    $ids = $tabla->pluck('profesor.id')->all();

    $posBasica = array_search($profBasica->id, $ids);
    $posMedia = array_search($profMedia->id, $ids);
    $posAsignatura = array_search($profAsignatura->id, $ids);

    expect($posBasica)->toBeLessThan($posMedia)
        ->and($posMedia)->toBeLessThan($posAsignatura);
});
