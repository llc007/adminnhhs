<?php

use App\Models\TiTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('guests are redirected to login from ti tareas page', function () {
    $response = $this->get(route('ti.tareas.index'));
    $response->assertRedirect(route('login'));
});

test('authorized ti staff or administrators can visit the ti tareas page', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['ti']);
    $this->actingAs($user);

    $response = $this->get(route('ti.tareas.index'));
    $response->assertOk();
});

test('ti staff can create a new recurring ti task', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['ti']);
    $this->actingAs($user);

    Livewire::test('pages::ti.tareas.index')
        ->call('abrirModalCrear')
        ->set('titulo', 'Revisión Diaria de Servidores NAS')
        ->set('descripcion', 'Comprobar espacio libre y temperatura')
        ->set('frecuencia', 'diaria')
        ->set('prioridad', 'alta')
        ->set('categoria', 'Servidores')
        ->set('fecha_programada', now()->format('Y-m-d'))
        ->set('es_recurrente', true)
        ->call('guardarTarea')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('ti_tasks', [
        'titulo' => 'Revisión Diaria de Servidores NAS',
        'frecuencia' => 'diaria',
        'prioridad' => 'alta',
        'categoria' => 'Servidores',
        'estado' => 'pendiente',
        'es_recurrente' => 1,
    ]);
});

test('completing a recurring daily task creates the next occurrence for tomorrow', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['ti']);
    $this->actingAs($user);

    $task = TiTask::create([
        'titulo' => 'Respaldo Diario BD',
        'descripcion' => 'Exportar dump MySQL',
        'frecuencia' => 'diaria',
        'prioridad' => 'critica',
        'categoria' => 'Respaldos',
        'estado' => 'pendiente',
        'fecha_programada' => now()->format('Y-m-d'),
        'creado_por' => $user->id,
        'es_recurrente' => true,
    ]);

    Livewire::test('pages::ti.tareas.index')
        ->call('abrirModalCompletar', $task->id)
        ->set('notas_cierre', 'Respaldo exitoso 5GB')
        ->call('confirmarCompletar')
        ->assertHasNoErrors();

    $task->refresh();
    expect($task->estado)->toBe('completada');
    expect($task->fecha_completada)->not->toBeNull();
    expect($task->notas_cierre)->toBe('Respaldo exitoso 5GB');

    // Next recurrence should be created
    $siguiente = TiTask::where('parent_id', $task->id)->first();
    expect($siguiente)->not->toBeNull();
    expect($siguiente->titulo)->toBe('Respaldo Diario BD');
    expect($siguiente->estado)->toBe('pendiente');
    expect($siguiente->fecha_programada->format('Y-m-d'))->toBe(now()->addDay()->format('Y-m-d'));
});

test('filtering tasks by frequency tab works', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['ti']);
    $this->actingAs($user);

    $diaria = TiTask::create([
        'titulo' => 'Tarea Diaria X',
        'frecuencia' => 'diaria',
        'prioridad' => 'media',
        'estado' => 'pendiente',
        'fecha_programada' => now()->format('Y-m-d'),
        'creado_por' => $user->id,
    ]);

    $semestral = TiTask::create([
        'titulo' => 'Mantenimiento Semestral Proyectores',
        'frecuencia' => 'semestral',
        'prioridad' => 'alta',
        'estado' => 'pendiente',
        'fecha_programada' => now()->format('Y-m-d'),
        'creado_por' => $user->id,
    ]);

    Livewire::test('pages::ti.tareas.index')
        ->assertSee('Tarea Diaria X')
        ->assertSee('Mantenimiento Semestral Proyectores')
        ->set('frecuenciaTab', 'diaria')
        ->assertSee('Tarea Diaria X')
        ->assertDontSee('Mantenimiento Semestral Proyectores')
        ->set('frecuenciaTab', 'semestral')
        ->assertSee('Mantenimiento Semestral Proyectores')
        ->assertDontSee('Tarea Diaria X');
});
