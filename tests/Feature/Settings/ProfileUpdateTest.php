<?php

use App\Models\User;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create([
        'nombres' => 'TEST',
        'apellido_pat' => 'USER',
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('nombres', 'NEW')
        ->set('apellido_pat', 'NAME')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->nombres)->toEqual('NEW');
    expect($user->apellido_pat)->toEqual('NAME');
});

test('profile information can be updated with empty optional fields without database error', function () {
    $user = User::factory()->create([
        'nombres' => 'ROBERTO',
        'apellido_pat' => 'MONDACA',
        'apellido_mat' => 'CASTRO',
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('nombres', 'ROBERTO')
        ->set('apellido_pat', 'MONDACA')
        ->set('apellido_mat', 'CASTRO')
        ->set('rut_numero', '')
        ->set('fecha_nacimiento', '')
        ->set('telefono', '')
        ->set('direccion', '')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->fecha_nacimiento)->toBeNull();
    expect($user->rut_numero)->toBeNull();
    expect($user->telefono)->toBeNull();
    expect($user->direccion)->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
