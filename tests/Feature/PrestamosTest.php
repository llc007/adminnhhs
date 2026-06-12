<?php

use App\Models\ArticuloInventario;
use App\Models\Prestamo;
use App\Models\User;
use App\Notifications\PrestamoRegistrado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('guests are redirected to the login page from loans pages', function () {
    $response = $this->get(route('ti.prestamos.index'));
    $response->assertRedirect(route('login'));

    $response2 = $this->get(route('ti.prestamos.crear'));
    $response2->assertRedirect(route('login'));

    $response3 = $this->get(route('ti.prestamos.mis_prestamos'));
    $response3->assertRedirect(route('login'));
});

test('authenticated administrators or ti staff can visit the loans page', function () {
    $user = User::factory()->create();
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['ti'])]);
    $this->actingAs($user);

    $response = $this->get(route('ti.prestamos.index'));
    $response->assertOk();

    $response2 = $this->get(route('ti.prestamos.crear'));
    $response2->assertOk();
});

test('regular teachers can only access their own mis_prestamos page', function () {
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

    // Regular docente should NOT be able to access management
    $response = $this->get(route('ti.prestamos.index'));
    $response->assertStatus(403); // Or redirect depending on middleware. Let's see: the middleware role:ti,administrador,superadmin aborts with 403 or redirects. In our routes, we protect it with this role middleware.

    $response2 = $this->get(route('ti.prestamos.crear'));
    $response2->assertStatus(403);

    // Docente CAN access mis_prestamos
    $response3 = $this->get(route('ti.prestamos.mis_prestamos'));
    $response3->assertOk();
});

test('submitting a new loan registration works and redirects and sends notification', function () {
    Notification::fake();

    $user = User::factory()->create();
    $docente = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['ti'])]);

    $docente->update(['current_school_id' => $schoolId]);
    $docente->schools()->attach($schoolId, ['roles' => json_encode(['docente'])]);

    $this->actingAs($user);

    // Create an inventory article to link
    $articulo = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'TEC-LAP-001',
        'nombre' => 'Laptop de Prueba',
        'categoria' => 'Tecnología',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad',
        'numero_serie' => '12345678',
        'cantidad' => 1,
        'fecha_ingreso' => now()->toDateString(),
        'estado_conservacion' => 'excelente',
    ]);

    Livewire::test('pages::ti.prestamos.crear')
        ->set('user_id', $docente->id)
        ->set('search_articulo', 'Laptop de Prueba')
        ->call('seleccionarArticulo', $articulo->id)
        ->set('cantidad', 1)
        ->set('fecha_prestamo', now()->toDateString())
        ->set('fecha_devolucion_estimada', now()->addWeek()->toDateString())
        ->set('observaciones', 'Ninguna')
        ->call('guardar')
        ->assertRedirect(route('ti.prestamos.index'));

    $this->assertDatabaseHas('prestamos', [
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'articulo_inventario_id' => $articulo->id,
        'nombre_articulo' => 'Laptop de Prueba',
        'estado' => 'prestado',
    ]);

    Notification::assertSentTo(
        $docente,
        PrestamoRegistrado::class
    );
});

test('ti staff can receive a returned loan', function () {
    $user = User::factory()->create();
    $docente = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['ti'])]);

    $docente->update(['current_school_id' => $schoolId]);
    $docente->schools()->attach($schoolId, ['roles' => json_encode(['docente'])]);

    $this->actingAs($user);

    $prestamo = Prestamo::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'nombre_articulo' => 'Laptop de Prueba',
        'cantidad' => 1,
        'fecha_prestamo' => now()->toDateString(),
        'fecha_devolucion_estimada' => now()->addWeek()->toDateString(),
        'estado' => 'prestado',
        'creado_por_user_id' => $user->id,
    ]);

    Livewire::test('pages::ti.prestamos.index')
        ->call('abrirDevolucion', $prestamo->id)
        ->set('observacionesDevolucion', 'Devuelto ok')
        ->call('procesarDevolucion');

    $prestamo->refresh();
    expect($prestamo->estado)->toBe('devuelto');
    expect($prestamo->fecha_devolucion_real)->not->toBeNull();
    expect($prestamo->recibido_por_user_id)->toBe($user->id);
    expect($prestamo->observaciones)->toContain('Devuelto ok');
});
