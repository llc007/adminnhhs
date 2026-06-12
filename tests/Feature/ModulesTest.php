<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('guests are redirected to the login page from modules management', function () {
    $response = $this->get(route('admin.modules'));
    $response->assertRedirect(route('login'));
});

test('regular teachers cannot visit the modules management page', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['docente'])]);

    $this->actingAs($user);

    $response = $this->get(route('admin.modules'));
    $response->assertStatus(403);
});

test('administrators can visit the modules management page', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['administrador'])]);

    $this->actingAs($user);

    $response = $this->get(route('admin.modules'));
    $response->assertOk();
});

test('modules configuration can be updated by administrators', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['administrador'])]);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.modules')
        ->set('modulos.entrevistas', false)
        ->set('modulos.estudiantes', true)
        ->set('modulos.adquisiciones', false)
        ->set('modulos.prestamos', true)
        ->call('save');

    $response->assertHasNoErrors();

    $school = School::find($schoolId);
    expect($school->modulos_publicados)->toEqual([
        'entrevistas' => false,
        'estudiantes' => true,
        'adquisiciones' => false,
        'prestamos' => true,
    ]);
});
