<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['administrador']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated assistants are redirected to mis prestamos', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['asistente']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('ti.prestamos.mis_prestamos'));
});
