<?php

use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

test('get request to livewire update endpoint redirects to referer or fallback instead of 405', function () {
    $updatePath = EndpointResolver::updatePath();

    $response = $this->withHeader('Referer', 'http://localhost/atrasos')
        ->get($updatePath);

    $response->assertRedirect('http://localhost/atrasos');
    $response->assertStatus(302);
});

test('get request to livewire update endpoint without referer redirects to dashboard', function () {
    $updatePath = EndpointResolver::updatePath();

    $response = $this->get($updatePath);

    $response->assertRedirect(route('dashboard'));
    $response->assertStatus(302);
});

test('method not allowed exception on arbitrary livewire update path redirects instead of failing with 405', function () {
    Route::post('/livewire-custom/update', fn () => 'ok');

    $response = $this->withHeader('Referer', 'http://localhost/inventario')
        ->get('/livewire-custom/update');

    $response->assertRedirect('http://localhost/inventario');
});

test('post request to livewire update endpoint is still routed to livewire update handler', function () {
    $updatePath = EndpointResolver::updatePath();

    $response = $this->postJson($updatePath, []);

    // Without X-Livewire header, Livewire's RequireLivewireHeaders middleware aborts with 404
    $response->assertStatus(404);
});
