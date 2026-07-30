<?php

use App\Models\AnuncioAgenda;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function setupAnunciosTestEnv()
{
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test High School',
        'domain' => 'testhigh.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

    Permission::findOrCreate('escribir-mensajes-agenda', 'web');
    Permission::findOrCreate('ver-entrevistas-propias', 'web');

    $adminRole = Role::findOrCreate('administrador', 'web');
    $adminRole->givePermissionTo(['escribir-mensajes-agenda', 'ver-entrevistas-propias']);

    $userConPermiso = User::factory()->create(['current_school_id' => $schoolId]);
    $userConPermiso->syncRolesForSchool($schoolId, ['administrador']);

    $userSinPermiso = User::factory()->create(['current_school_id' => $schoolId]);
    $userSinPermiso->givePermissionTo('ver-entrevistas-propias');

    return [$userConPermiso, $userSinPermiso, $schoolId];
}

test('authorized user can publish an announcement on agenda board', function () {
    [$userConPermiso, $userSinPermiso, $schoolId] = setupAnunciosTestEnv();

    Livewire::actingAs($userConPermiso)
        ->test('pages::entrevistas.agenda')
        ->call('abrirModalNuevoAnuncio')
        ->set('tituloAnuncio', 'Reunión de Apoderados 2ºB')
        ->set('cuerpoAnuncio', 'Se recuerda que mañana a las 16:00 hrs se realizará la reunión mensual.')
        ->set('colorAnuncio', 'emerald')
        ->call('guardarAnuncio')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('anuncios_agenda', [
        'school_id' => $schoolId,
        'user_id' => $userConPermiso->id,
        'titulo' => 'Reunión de Apoderados 2ºB',
        'color' => 'emerald',
    ]);
});

test('unauthorized user cannot publish announcement on agenda board', function () {
    [$userConPermiso, $userSinPermiso, $schoolId] = setupAnunciosTestEnv();

    Livewire::actingAs($userSinPermiso)
        ->test('pages::entrevistas.agenda')
        ->call('abrirModalNuevoAnuncio')
        ->assertStatus(403);
});

test('authorized user can edit and delete announcements', function () {
    [$userConPermiso, $userSinPermiso, $schoolId] = setupAnunciosTestEnv();

    $anuncio = AnuncioAgenda::create([
        'school_id' => $schoolId,
        'user_id' => $userConPermiso->id,
        'titulo' => 'Aviso Temporal',
        'cuerpo' => 'Cuerpo de mensaje temporal para edición.',
        'color' => 'blue',
        'activo' => true,
    ]);

    Livewire::actingAs($userConPermiso)
        ->test('pages::entrevistas.agenda')
        ->call('editarAnuncio', $anuncio->id)
        ->set('tituloAnuncio', 'Aviso Actualizado')
        ->call('guardarAnuncio')
        ->assertHasNoErrors();

    expect($anuncio->refresh()->titulo)->toBe('Aviso Actualizado');

    Livewire::actingAs($userConPermiso)
        ->test('pages::entrevistas.agenda')
        ->call('eliminarAnuncio', $anuncio->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('anuncios_agenda', ['id' => $anuncio->id]);
});

test('user can react with stickers to an announcement', function () {
    [$userConPermiso, $userSinPermiso, $schoolId] = setupAnunciosTestEnv();

    $anuncio = AnuncioAgenda::create([
        'school_id' => $schoolId,
        'user_id' => $userConPermiso->id,
        'titulo' => 'Aviso Importante',
        'cuerpo' => 'Mensaje para reacción.',
        'color' => 'amber',
        'activo' => true,
    ]);

    // 1. User reacts with thumbs up
    Livewire::actingAs($userSinPermiso)
        ->test('pages::entrevistas.agenda')
        ->call('reaccionarAnuncio', $anuncio->id, '👍')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('anuncio_reacciones', [
        'anuncio_agenda_id' => $anuncio->id,
        'user_id' => $userSinPermiso->id,
        'reaction' => '👍',
    ]);

    // 2. User toggles reaction off
    Livewire::actingAs($userSinPermiso)
        ->test('pages::entrevistas.agenda')
        ->call('reaccionarAnuncio', $anuncio->id, '👍')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('anuncio_reacciones', [
        'anuncio_agenda_id' => $anuncio->id,
        'user_id' => $userSinPermiso->id,
        'reaction' => '👍',
    ]);
});
