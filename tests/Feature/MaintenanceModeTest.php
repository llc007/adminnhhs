<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function setupMaintenanceEnv(bool $modoMantenimiento = false)
{
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Colegio Test Mantenimiento',
        'domain' => 'mantenimiento.com',
        'modulos_publicados' => json_encode([
            'entrevistas' => true,
            'estudiantes' => true,
            'adquisiciones' => true,
            'prestamos' => true,
            'envio_correos' => true,
            'modo_mantenimiento' => $modoMantenimiento,
            'mensaje_mantenimiento' => 'Estamos realizando mejoras programadas.',
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $superadmin = User::factory()->create(['current_school_id' => $schoolId]);
    $superadmin->syncRolesForSchool($schoolId, ['superadmin']);

    $docente = User::factory()->create(['current_school_id' => $schoolId]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

    return [$superadmin, $docente, $schoolId];
}

test('superadmin can enable and disable maintenance mode in modules component', function () {
    [$superadmin, $docente, $schoolId] = setupMaintenanceEnv(false);
    $this->actingAs($superadmin);

    Livewire::test('pages::admin.modules')
        ->set('modulos.modo_mantenimiento', true)
        ->set('modulos.mensaje_mantenimiento', 'Cerrado por mantenimiento de servidores.')
        ->call('save')
        ->assertHasNoErrors();

    $school = DB::table('schools')->where('id', $schoolId)->first();
    $modulos = json_decode($school->modulos_publicados, true);

    expect($modulos['modo_mantenimiento'])->toBeTrue();
    expect($modulos['mensaje_mantenimiento'])->toBe('Cerrado por mantenimiento de servidores.');
});

test('docente and administrador users get 503 maintenance page when maintenance mode is active', function () {
    [$superadmin, $docente, $schoolId] = setupMaintenanceEnv(true);

    $adminUser = User::factory()->create(['current_school_id' => $schoolId]);
    $adminUser->givePermissionTo('ver-entrevistas-propias');
    $adminUser->syncRolesForSchool($schoolId, ['administrador']);

    // Docente blocked
    $this->actingAs($docente);
    $responseDocente = $this->get(route('entrevistas.index'));
    $responseDocente->assertStatus(503);
    $responseDocente->assertSee('Estamos actualizando el sistema');

    // Administrador bypasses
    $this->actingAs($adminUser);
    $responseAdmin = $this->get(route('entrevistas.agenda'));
    $responseAdmin->assertOk();
});

test('superadmin bypasses maintenance mode when active', function () {
    [$superadmin, $docente, $schoolId] = setupMaintenanceEnv(true);

    $this->actingAs($superadmin);

    $response = $this->get(route('admin.modules'));
    $response->assertOk();
    $response->assertSee('Modo Mantenimiento del Sistema');
});
