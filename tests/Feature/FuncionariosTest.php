<?php

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('it updates ultimo_ingreso_at on user login event', function () {
    $user = User::factory()->create(['ultimo_ingreso_at' => null]);

    expect($user->ultimo_ingreso_at)->toBeNull();

    // Trigger standard login event
    Event::dispatch(new Login('web', $user, false));

    $user->refresh();
    expect($user->ultimo_ingreso_at)->not->toBeNull();
    expect($user->ultimo_ingreso_at->isToday())->toBeTrue();
});

test('administrators can view last login column in staff list', function () {
    $admin = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $admin->update(['current_school_id' => $schoolId]);
    $admin->schools()->attach($schoolId, ['roles' => json_encode(['administrador'])]);

    $staff = User::factory()->create([
        'ultimo_ingreso_at' => now()->subHours(2),
        'nombres' => 'JUAN PEDRO',
        'apellido_pat' => 'LOPEZ',
    ]);
    $staff->schools()->attach($schoolId, ['roles' => json_encode(['docente'])]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::usuarios.funcionarios.index')
        ->assertSee('JUAN PEDRO')
        ->assertSee('hace 2 horas'); // diffForHumans representation
});

test('it updates ultimo_ingreso_at on subsequent requests via middleware if older than 3 hours', function () {
    $user = User::factory()->create([
        'ultimo_ingreso_at' => now()->subMinutes(190),
    ]);

    $this->actingAs($user);

    $this->get('/dashboard');

    $user->refresh();
    expect($user->ultimo_ingreso_at->isToday())->toBeTrue();
    expect($user->ultimo_ingreso_at->gt(now()->subMinute()))->toBeTrue();
});

test('it does not update ultimo_ingreso_at via middleware if updated less than 3 hours ago', function () {
    $initialTime = now()->subMinutes(60);
    $user = User::factory()->create([
        'ultimo_ingreso_at' => $initialTime,
    ]);

    $this->actingAs($user);

    $this->get('/dashboard');

    $user->refresh();
    expect($user->ultimo_ingreso_at->timestamp)->toEqual($initialTime->timestamp);
});

test('assigning a real role to a pending user via index modal removes the externo role', function () {
    $admin = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $admin->update(['current_school_id' => $schoolId]);
    $admin->schools()->attach($schoolId, ['roles' => json_encode(['administrador'])]);

    $pendingStaff = User::factory()->create([
        'nombres' => 'PENDING',
        'apellido_pat' => 'STAFF',
    ]);
    $pendingStaff->schools()->attach($schoolId, ['roles' => json_encode(['externo'])]);

    $this->actingAs($admin);

    Livewire::test('pages::usuarios.funcionarios.index')
        ->call('abrirEditar', $pendingStaff->id)
        ->set('roles', ['externo', 'docente']) // Simulate selecting docente on a pending user
        ->call('guardar');

    $pendingStaff->refresh();
    $roles = json_decode($pendingStaff->schools()->where('school_id', $schoolId)->first()->pivot->roles, true);

    expect($roles)->toContain('docente');
    expect($roles)->not->toContain('externo');
});
