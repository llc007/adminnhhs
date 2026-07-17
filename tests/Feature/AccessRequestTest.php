<?php

use App\Models\User;
use App\Notifications\SolicitudAcceso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('unregistered users with only externo role are redirected to sin-permiso', function () {
    $user = User::factory()->create();

    // Attach school with default 'externo' role
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['externo']);

    // Try to visit dashboard (requires administrador/directivo/superadmin)
    $response = $this->actingAs($user)->get(route('dashboard'));

    // Should redirect to /sin-permiso
    $response->assertRedirect(route('sin-permiso'));
});

test('unregistered users can render sin-permiso page and submit access requests', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Setup user with 'externo' role
    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['externo']);

    // Setup admin with 'administrador' role in same school
    $admin->update(['current_school_id' => $schoolId]);
    $admin->syncRolesForSchool($schoolId, ['administrador']);

    // 1. Assert the page renders
    $component = Livewire::actingAs($user)->test('pages::auth.sin-permiso');
    $component->assertSee('Permiso Requerido');

    // 2. Mock notifications
    Notification::fake();

    // 3. Request access
    $response = $component->call('solicitarAcceso', 'docente');

    // Should redirect to login with a status message
    $response->assertRedirect(route('login'));
    $this->assertGuest();

    // Notification should be sent to the administrator
    Notification::assertSentTo(
        $admin,
        SolicitudAcceso::class,
        function ($notification) use ($user) {
            return $notification->user->id === $user->id && $notification->rol === 'docente';
        }
    );
});

test('unregistered users can logout from sin-permiso page', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('pages::auth.sin-permiso');

    $response = $component->call('logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('inspectors are redirected to recepcion when visiting the dashboard', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['inspector', 'docente']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('entrevistas.recepcion'));
});

test('teachers are redirected to agenda when visiting the dashboard', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['docente']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('entrevistas.agenda'));
});

test('users with gmail.com emails are completely blocked and redirected to login', function () {
    $user = User::factory()->create([
        'email' => 'testuser@gmail.com',
    ]);

    $response = $this->actingAs($user)->get(route('sin-permiso'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error', 'Solo se permite el acceso a correos institucionales.');
    $this->assertGuest();
});
