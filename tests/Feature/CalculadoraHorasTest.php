<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('funcionarios.calculadora_horas'));
    $response->assertRedirect(route('login'));
});

test('authenticated users with admin role can visit the calculator', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['superadmin']);

    $this->actingAs($user);

    $response = $this->get(route('funcionarios.calculadora_horas'));
    $response->assertOk();
});

test('the calculator page computes weekly hours correctly', function () {
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

    // Test default 42h preset
    $component = Livewire::test('pages::usuarios.funcionarios.calculadora_horas')
        ->call('cargarPreset', '5dias_42h')
        ->assertSet('objetivoHoras', 42);

    expect($component->get('calculo')['total_decimal'])->toBe(42.0);

    // Test 40h preset
    $component = Livewire::test('pages::usuarios.funcionarios.calculadora_horas')
        ->call('cargarPreset', '5dias_40h')
        ->assertSet('objetivoHoras', 40);

    expect($component->get('calculo')['total_decimal'])->toBe(40.0);
});
