<?php

use App\Models\CategoriaEntrevista;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function setupCategoriaEnv()
{
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'School Test Categorias',
        'domain' => 'cat-test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $admin = User::factory()->create(['current_school_id' => $schoolId]);
    $admin->syncRolesForSchool($schoolId, ['administrador']);

    $docente = User::factory()->create(['current_school_id' => $schoolId]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    $admin->givePermissionTo(Permission::findOrCreate('crear-entrevistas', 'web'));
    $docente->givePermissionTo(Permission::findOrCreate('crear-entrevistas', 'web'));

    return [$schoolId, $admin, $docente];
}

test('only admins can manage entrevista categories', function () {
    [$schoolId, $admin, $docente] = setupCategoriaEnv();

    // Docente cannot access modal or methods
    Livewire::actingAs($docente)
        ->test('pages::entrevistas.crear')
        ->assertDontSee('Mantenedor Categorías')
        ->call('abrirModalCategorias')
        ->assertStatus(403);

    // Admin can see button and create new category
    Livewire::actingAs($admin)
        ->test('pages::entrevistas.crear')
        ->assertSee('Mantenedor Categorías')
        ->set('nuevaCategoriaNombre', 'Categoría Especial PIE')
        ->set('nuevaCategoriaDesc', 'Entrevistas de seguimiento PIE')
        ->call('guardarCategoria')
        ->assertSet('motivo', 'Categoría Especial PIE');

    $cat = CategoriaEntrevista::where('school_id', $schoolId)->where('nombre', 'Categoría Especial PIE')->first();
    expect($cat)->not->toBeNull();
    expect($cat->descripcion)->toBe('Entrevistas de seguimiento PIE');
});
