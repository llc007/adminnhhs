<?php

use App\Models\EvaluacionDirectivo;
use App\Models\EvaluacionDirectivoAsignacion;
use App\Models\School;
use App\Models\User;
use Livewire\Livewire;

function createSchoolForDirectivos(): array
{
    $school = School::create(['name' => 'Colegio Directivos Test', 'domain' => 'directivos.cl']);

    $comision = User::factory()->create(['current_school_id' => $school->id]);
    $comision->syncRolesForSchool($school->id, ['docente', 'comision_ed']);

    $capellan = User::factory()->create([
        'nombres' => 'ROBERTO',
        'apellido_pat' => 'CAPELLAN',
        'current_school_id' => $school->id,
    ]);
    $capellan->syncRolesForSchool($school->id, ['asistente']);

    $utpBasica = User::factory()->create([
        'nombres' => 'MARIA',
        'apellido_pat' => 'UTPBASICA',
        'current_school_id' => $school->id,
    ]);
    $utpBasica->syncRolesForSchool($school->id, ['directivo']);

    $utpMedia = User::factory()->create([
        'nombres' => 'PEDRO',
        'apellido_pat' => 'UTPMEDIA',
        'current_school_id' => $school->id,
    ]);
    $utpMedia->syncRolesForSchool($school->id, ['directivo']);

    return [$school, $comision, $capellan, $utpBasica, $utpMedia];
}

test('all 8 department questionnaires have their expected question counts and custom no_aplica options', function () {
    expect(count(EvaluacionDirectivo::preguntasDe('capellania')))->toBe(9)
        ->and(count(EvaluacionDirectivo::opcionesNoAplica('capellania', 3)))->toBe(2)
        ->and(count(EvaluacionDirectivo::preguntasDe('coordinador_convivencia')))->toBe(10)
        ->and(count(EvaluacionDirectivo::preguntasDe('encargados_convivencia')))->toBe(12)
        ->and(count(EvaluacionDirectivo::preguntasDe('evaluadores')))->toBe(11)
        ->and(count(EvaluacionDirectivo::preguntasDe('orientacion')))->toBe(14)
        ->and(count(EvaluacionDirectivo::preguntasDe('psicologas')))->toBe(11)
        ->and(count(EvaluacionDirectivo::preguntasDe('psicopedagogas')))->toBe(12)
        ->and(count(EvaluacionDirectivo::preguntasDe('utp')))->toBe(10);
});

test('comision_ed can assign staff members to departments with cycle tags', function () {
    [$school, $comision, , $utpBasica, $utpMedia] = createSchoolForDirectivos();

    $this->actingAs($comision);

    Livewire::test('pages::encuestas.directivos_resultados')
        ->set('asigDepartamento', 'utp')
        ->set('asigUserId', $utpBasica->id)
        ->set('asigEtiqueta', 'Enseñanza Básica')
        ->call('agregarAsignacion')
        ->set('asigDepartamento', 'utp')
        ->set('asigUserId', $utpMedia->id)
        ->set('asigEtiqueta', 'Enseñanza Media')
        ->call('agregarAsignacion');

    expect(EvaluacionDirectivoAsignacion::where('school_id', $school->id)->where('departamento', 'utp')->count())->toBe(2);
});

test('department without assigned members evaluates the department in general, single-member goes straight to person, and multi-member asks to pick', function () {
    [$school, $comision, $capellan, $utpBasica, $utpMedia] = createSchoolForDirectivos();

    // Capellanía SIN integrantes asignados -> se evalúa al departamento en general (evaluado_id = null)
    // Coordinador Convivencia con 1 integrante asignado -> salta directo a evaluar a esa persona
    EvaluacionDirectivoAsignacion::create([
        'school_id' => $school->id,
        'user_id' => $capellan->id,
        'departamento' => 'coordinador_convivencia',
        'etiqueta' => 'General',
    ]);

    // 2 integrantes en UTP (Básica y Media)
    EvaluacionDirectivoAsignacion::create([
        'school_id' => $school->id,
        'user_id' => $utpBasica->id,
        'departamento' => 'utp',
        'etiqueta' => 'Enseñanza Básica',
    ]);
    EvaluacionDirectivoAsignacion::create([
        'school_id' => $school->id,
        'user_id' => $utpMedia->id,
        'departamento' => 'utp',
        'etiqueta' => 'Enseñanza Media',
    ]);

    // 1) En Capellanía (0 integrantes), entra directo a evaluar al Departamento en General
    $component = Livewire::test('pages::encuestas.directivos_kiosco', ['schoolId' => $school->id])
        ->set('pinIngresado', '9012')
        ->call('validarPin')
        ->assertSet('pinValidado', true)
        ->call('seleccionarDepartamento', 'capellania')
        ->assertSet('paso', 'encuesta')
        ->assertSet('evaluadoId', null)
        ->assertSee('Evaluación General del Departamento');

    // Responder las 9 preguntas de Capellanía: P3 con Respuesta 6 (-2), P8 y P9 con Respuesta 5 (-1), y las otras 6 con Destacado (3)
    foreach (range(1, 9) as $num) {
        if ($num === 3) {
            $component->call('responderPregunta', 3, -2);
        } elseif (in_array($num, [8, 9], true)) {
            $component->call('responderPregunta', $num, -1);
        } else {
            $component->call('responderPregunta', $num, 3);
        }
    }

    $component->call('enviarEncuesta')
        ->assertSet('paso', 'completado');

    $evalCapellaniaGeneral = EvaluacionDirectivo::where('departamento', 'capellania')->first();
    expect($evalCapellaniaGeneral)->not->toBeNull()
        ->and($evalCapellaniaGeneral->evaluado_id)->toBeNull()
        ->and($evalCapellaniaGeneral->promedio)->toBe(3.0)
        ->and($evalCapellaniaGeneral->puntaje_obtenido)->toBe(27)
        ->and($evalCapellaniaGeneral->puntaje_maximo)->toBe(27);

    // 2) En Coordinador Convivencia (1 integrante), entra directo a evaluar a esa persona
    $component->call('volverADepartamentos')
        ->call('seleccionarDepartamento', 'coordinador_convivencia')
        ->assertSet('paso', 'encuesta')
        ->assertSet('evaluadoId', $capellan->id)
        ->assertSee('ROBERTO CAPELLAN');

    // 3) En UTP (2 integrantes), pasa primero al paso 'persona' para elegir Básica o Media
    $component->call('volverADepartamentos')
        ->call('seleccionarDepartamento', 'utp')
        ->assertSet('paso', 'persona')
        ->assertSee('MARIA UTPBASICA')
        ->assertSee('Enseñanza Básica')
        ->assertSee('PEDRO UTPMEDIA')
        ->assertSee('Enseñanza Media')
        ->call('seleccionarPersona', $utpMedia->id)
        ->assertSet('paso', 'encuesta')
        ->assertSet('evaluadoId', $utpMedia->id);

    // 4) En la tabla de resultados, Capellanía muestra la fila de Evaluación General del Departamento
    $this->actingAs($comision);
    Livewire::test('pages::encuestas.directivos_resultados')
        ->assertSee('Capellanía')
        ->assertSee('Evaluación General del Departamento')
        ->assertSee('27.0')
        ->call('verDetalleEvaluado', 0)
        ->assertSet('modalDetalle', true);
});
