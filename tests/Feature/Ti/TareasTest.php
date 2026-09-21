<?php

use App\Models\TiTask;
use App\Models\User;
use Carbon\Carbon;
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

test('filtering tasks by frequency tab and calendar date works', function () {
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

    $todayStr = now('America/Santiago')->toDateString();

    $diaria = TiTask::create([
        'titulo' => 'Tarea Diaria X',
        'frecuencia' => 'diaria',
        'prioridad' => 'media',
        'estado' => 'pendiente',
        'fecha_programada' => $todayStr,
        'creado_por' => $user->id,
    ]);

    $semestral = TiTask::create([
        'titulo' => 'Mantenimiento Semestral Proyectores',
        'frecuencia' => 'semestral',
        'prioridad' => 'alta',
        'estado' => 'pendiente',
        'fecha_programada' => $todayStr,
        'creado_por' => $user->id,
    ]);

    Livewire::test('pages::ti.tareas.index')
        ->set('frecuenciaTab', 'diaria')
        ->assertSee('Tarea Diaria X')
        ->assertDontSee('Mantenimiento Semestral Proyectores')
        ->set('frecuenciaTab', 'semestral')
        ->assertSee('Mantenimiento Semestral Proyectores')
        ->assertDontSee('Tarea Diaria X');
});

test('selecting calendar date filters tasks for that date and reopening completed tasks works', function () {
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

    $todayStr = now('America/Santiago')->toDateString();
    $pastDateStr = now('America/Santiago')->subDays(5)->toDateString();

    $taskHoy = TiTask::create([
        'titulo' => 'Tarea de Hoy',
        'frecuencia' => 'diaria',
        'estado' => 'pendiente',
        'fecha_programada' => $todayStr,
        'creado_por' => $user->id,
    ]);

    $taskPasada = TiTask::create([
        'titulo' => 'Tarea de Hace 5 Dias',
        'frecuencia' => 'diaria',
        'estado' => 'completada',
        'fecha_programada' => $pastDateStr,
        'fecha_completada' => now(),
        'notas_cierre' => 'Revisión efectuada',
        'creado_por' => $user->id,
        'es_recurrente' => false,
    ]);

    Livewire::test('pages::ti.tareas.index')
        ->set('fechaSeleccionada', $todayStr)
        ->assertSee('Tarea de Hoy')
        ->assertDontSee('Tarea de Hace 5 Dias')
        ->set('fechaSeleccionada', $pastDateStr)
        ->assertSee('Tarea de Hace 5 Dias')
        ->assertDontSee('Tarea de Hoy')
        ->call('reabrirTarea', $taskPasada->id);

    expect($taskPasada->refresh()->estado)->toBe('pendiente');
});

test('ti staff can save progress notes on an active task without completing or archiving it', function () {
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

    $todayStr = now('America/Santiago')->toDateString();

    $task = TiTask::create([
        'titulo' => 'Mantenimiento Servidor BD',
        'frecuencia' => 'semanal',
        'estado' => 'pendiente',
        'fecha_programada' => $todayStr,
        'creado_por' => $user->id,
    ]);

    Livewire::test('pages::ti.tareas.index')
        ->call('abrirModalCompletar', $task->id)
        ->set('notas_cierre', 'Paso 1 completado: Limpieza física realizada')
        ->call('guardarAvance')
        ->assertHasNoErrors();

    $task->refresh();
    expect($task->estado)->toBe('en_progreso');
    expect($task->notas_cierre)->toBe('Paso 1 completado: Limpieza física realizada');
    expect($task->fecha_completada)->toBeNull();
});

test('completing a task requires a non-empty closing note', function () {
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

    $todayStr = now('America/Santiago')->toDateString();

    $task = TiTask::create([
        'titulo' => 'Revisión Servidor Web',
        'frecuencia' => 'diaria',
        'estado' => 'pendiente',
        'fecha_programada' => $todayStr,
        'creado_por' => $user->id,
    ]);

    Livewire::test('pages::ti.tareas.index')
        ->call('abrirModalCompletar', $task->id)
        ->set('notas_cierre', '')
        ->call('confirmarCompletar')
        ->assertHasErrors(['notas_cierre']);
});

test('clicking task title opens modal with full task details', function () {
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

    $todayStr = now('America/Santiago')->toDateString();

    $task = TiTask::create([
        'titulo' => 'Revisión Wifi Ed Básaica Extensa',
        'descripcion' => 'Esta es una descripción extremadamente larga que supera los 50 caracteres para probar el truncado y la visualización completa dentro del modal.',
        'frecuencia' => 'diaria',
        'estado' => 'pendiente',
        'fecha_programada' => $todayStr,
        'creado_por' => $user->id,
    ]);

    Livewire::test('pages::ti.tareas.index')
        ->call('verDetalle', $task->id)
        ->assertSet('showModalUnificado', true)
        ->assertSet('selectedTask.id', $task->id)
        ->assertSee('Esta es una descripción extremadamente larga');
});

test('uncompleted past recurring tasks automatically project instances up to today', function () {
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

    $pastDateStr = now('America/Santiago')->subDays(7)->startOfWeek()->toDateString();
    $todayStr = now('America/Santiago')->toDateString();

    // Create a daily task scheduled 4 days ago that was never completed
    $task = TiTask::create([
        'titulo' => 'Revisión Servidor NAS Recurrente',
        'frecuencia' => 'diaria',
        'prioridad' => 'media',
        'estado' => 'pendiente',
        'fecha_programada' => $pastDateStr,
        'creado_por' => $user->id,
        'es_recurrente' => true,
    ]);

    // Mount livewire component for today's date
    Livewire::test('pages::ti.tareas.index')
        ->set('fechaSeleccionada', $todayStr)
        ->assertSee('Revisión Servidor NAS Recurrente');

    // Verify today's instance was created automatically
    $todayTask = TiTask::where('titulo', 'Revisión Servidor NAS Recurrente')
        ->whereDate('fecha_programada', $todayStr)
        ->first();

    expect($todayTask)->not->toBeNull();
    expect($todayTask->estado)->toBe('pendiente');
});

test('completing yesterday task does not create duplicate if today instance already exists', function () {
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

    $yesterdayStr = now('America/Santiago')->subDay()->toDateString();
    $todayStr = now('America/Santiago')->toDateString();

    $taskAyer = TiTask::create([
        'titulo' => 'Revisión CCTV Diaria',
        'frecuencia' => 'diaria',
        'prioridad' => 'media',
        'estado' => 'pendiente',
        'fecha_programada' => $yesterdayStr,
        'creado_por' => $user->id,
        'es_recurrente' => true,
    ]);

    $taskHoy = TiTask::create([
        'titulo' => 'Revisión CCTV Diaria',
        'frecuencia' => 'diaria',
        'prioridad' => 'media',
        'estado' => 'pendiente',
        'fecha_programada' => $todayStr,
        'creado_por' => $user->id,
        'parent_id' => $taskAyer->id,
        'es_recurrente' => true,
    ]);

    // Complete yesterday task
    $taskAyer->completar('OK ayer');

    // Count today instances
    $countToday = TiTask::where('titulo', 'Revisión CCTV Diaria')
        ->whereDate('fecha_programada', $todayStr)
        ->count();

    expect($countToday)->toBe(1);
});

test('completing a Friday daily task schedules next instance for Monday skipping weekend', function () {
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

    // Find next Friday
    $friday = now('America/Santiago')->next(Carbon::FRIDAY);
    $monday = $friday->copy()->addDays(3);

    $taskViernes = TiTask::create([
        'titulo' => 'Revisión Servidor Viernes',
        'frecuencia' => 'diaria',
        'prioridad' => 'media',
        'estado' => 'pendiente',
        'fecha_programada' => $friday->toDateString(),
        'creado_por' => $user->id,
        'es_recurrente' => true,
    ]);

    $siguiente = $taskViernes->completar('OK viernes');

    expect($siguiente)->not->toBeNull();
    expect($siguiente->fecha_programada->toDateString())->toBe($monday->toDateString());
});

test('uncompleted weekly task in previous week automatically generates instance for current week', function () {
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

    $lastWeekStartStr = now('America/Santiago')->subWeek()->startOfWeek()->toDateString();
    $currentWeekStartStr = now('America/Santiago')->startOfWeek()->toDateString();

    // Create a weekly task scheduled last week that was never completed
    $task = TiTask::create([
        'titulo' => 'Revisión Semanal de Servidores Backup',
        'frecuencia' => 'semanal',
        'prioridad' => 'alta',
        'estado' => 'pendiente',
        'fecha_programada' => $lastWeekStartStr,
        'creado_por' => $user->id,
        'es_recurrente' => true,
    ]);

    // Mount livewire component for today
    Livewire::test('pages::ti.tareas.index')
        ->set('frecuenciaTab', 'semanal')
        ->assertSee('Revisión Semanal de Servidores Backup');

    // Verify current week instance was created automatically
    $currentWeekTask = TiTask::where('titulo', 'Revisión Semanal de Servidores Backup')
        ->whereDate('fecha_programada', $currentWeekStartStr)
        ->first();

    expect($currentWeekTask)->not->toBeNull();
    expect($currentWeekTask->estado)->toBe('pendiente');
});
