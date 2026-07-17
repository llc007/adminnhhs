<?php

use App\Models\ArticuloInventario;
use App\Models\Prestamo;
use App\Models\User;
use App\Notifications\PrestamoRegistrado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

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
    $user->syncRolesForSchool($schoolId, ['ti']);
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
    $user->syncRolesForSchool($schoolId, ['docente']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    $permission = Permission::findOrCreate('ver-prestamos-propios', 'web');
    $user->givePermissionTo($permission);
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
    $user->syncRolesForSchool($schoolId, ['ti']);

    $docente->update(['current_school_id' => $schoolId]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

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
    $user->syncRolesForSchool($schoolId, ['ti']);

    $docente->update(['current_school_id' => $schoolId]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

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

test('loans dashboard separates active and returned loans using tabs', function () {
    $user = User::factory()->create();
    $docente = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['ti']);
    $docente->update(['current_school_id' => $schoolId]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

    $this->actingAs($user);

    // Create 1 active loan and 1 returned loan
    $activo = Prestamo::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'nombre_articulo' => 'Laptop Activo',
        'cantidad' => 1,
        'fecha_prestamo' => now()->toDateString(),
        'fecha_devolucion_estimada' => now()->addWeek()->toDateString(),
        'estado' => 'prestado',
        'creado_por_user_id' => $user->id,
    ]);

    $devuelto = Prestamo::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'nombre_articulo' => 'Mouse Devuelto',
        'cantidad' => 1,
        'fecha_prestamo' => now()->subDays(5)->toDateString(),
        'fecha_devolucion_estimada' => now()->subDays(2)->toDateString(),
        'fecha_devolucion_real' => now()->toDateString(),
        'estado' => 'devuelto',
        'creado_por_user_id' => $user->id,
        'recibido_por_user_id' => $user->id,
    ]);

    // Test activeTab default (activos) shows the active, hides the returned
    Livewire::test('pages::ti.prestamos.index')
        ->assertSet('activeTab', 'activos')
        ->assertSet('countActivos', 1)
        ->assertSee('Laptop Activo')
        ->assertDontSee('Mouse Devuelto')
        // Switch to devueltos tab
        ->set('activeTab', 'devueltos')
        ->assertSee('Mouse Devuelto')
        ->assertDontSee('Laptop Activo')
        // Return the active loan
        ->set('activeTab', 'activos')
        ->call('abrirDevolucion', $activo->id)
        ->set('observacionesDevolucion', 'Todo OK')
        ->call('procesarDevolucion')
        // Verify count decreases
        ->assertSet('countActivos', 0)
        ->assertDontSee('Laptop Activo')
        // Switch back to devueltos to see it there
        ->set('activeTab', 'devueltos')
        ->assertSee('Laptop Activo')
        ->assertSee('Mouse Devuelto');
});

test('ti staff can edit an active loan details', function () {
    $user = User::factory()->create();
    $docente1 = User::factory()->create();
    $docente2 = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['ti']);
    $docente1->update(['current_school_id' => $schoolId]);
    $docente1->syncRolesForSchool($schoolId, ['docente']);
    $docente2->update(['current_school_id' => $schoolId]);
    $docente2->syncRolesForSchool($schoolId, ['docente']);

    $this->actingAs($user);

    $prestamo = Prestamo::create([
        'school_id' => $schoolId,
        'user_id' => $docente1->id,
        'nombre_articulo' => 'Teclado Mecánico',
        'cantidad' => 1,
        'fecha_prestamo' => now()->toDateString(),
        'fecha_devolucion_estimada' => now()->addWeek()->toDateString(),
        'estado' => 'prestado',
        'creado_por_user_id' => $user->id,
    ]);

    // Open edit modal and modify values
    Livewire::test('pages::ti.prestamos.index')
        ->call('abrirEditar', $prestamo->id)
        ->assertSet('editDocenteId', $docente1->id)
        ->assertSet('editCantidad', 1)
        ->set('editDocenteId', $docente2->id)
        ->set('editCantidad', 3)
        ->set('editFechaDevolucionEstimada', now()->addDays(10)->toDateString())
        ->set('editObservaciones', 'Modificado por error tipográfico')
        ->call('guardarEdicion')
        ->assertHasNoErrors()
        ->assertSet('modalEditar', false);

    $prestamo->refresh();
    expect($prestamo->user_id)->toBe($docente2->id);
    expect($prestamo->cantidad)->toBe(3);
    expect($prestamo->fecha_devolucion_estimada->toDateString())->toBe(now()->addDays(10)->toDateString());
    expect($prestamo->observaciones)->toBe('Modificado por error tipográfico');
});

test('an active loan prevents the article from appearing in search suggestions and registering a new loan', function () {
    $user = User::factory()->create();
    $docente = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['ti']);
    $docente->update(['current_school_id' => $schoolId]);
    $docente->syncRolesForSchool($schoolId, ['docente']);

    $this->actingAs($user);

    // Create an inventory article
    $articulo = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'ASUS-LTP-001',
        'nombre' => 'Asus Notebook X515',
        'categoria' => 'Tecnología',
        'marca' => 'Asus',
        'modelo' => 'X515',
        'cantidad' => 1,
        'fecha_ingreso' => now()->toDateString(),
        'estado_conservacion' => 'bueno',
    ]);

    // Initially, it should be in the suggestions
    Livewire::test('pages::ti.prestamos.crear')
        ->set('search_articulo', 'Asus')
        ->assertCount('sugerencias', 1)
        ->assertSet('sugerencias.0.id', $articulo->id);

    // Create an active loan for this article
    $prestamo = Prestamo::create([
        'school_id' => $schoolId,
        'user_id' => $docente->id,
        'articulo_inventario_id' => $articulo->id,
        'nombre_articulo' => $articulo->nombre,
        'cantidad' => 1,
        'fecha_prestamo' => now()->toDateString(),
        'fecha_devolucion_estimada' => now()->addWeek()->toDateString(),
        'estado' => 'prestado',
        'creado_por_user_id' => $user->id,
    ]);

    // Now, search suggestions should NOT return the article
    Livewire::test('pages::ti.prestamos.crear')
        ->set('search_articulo', 'Asus')
        ->assertSet('sugerencias', []);

    // Try to register a loan for this article anyway (e.g. bypass suggestion)
    Livewire::test('pages::ti.prestamos.crear')
        ->set('user_id', $docente->id)
        ->set('search_articulo', 'Asus')
        ->call('seleccionarArticulo', $articulo->id)
        ->set('cantidad', 1)
        ->set('fecha_prestamo', now()->toDateString())
        ->set('fecha_devolucion_estimada', now()->addWeek()->toDateString())
        ->call('guardar')
        ->assertHasErrors(['search_articulo']);

    // Mark the loan as returned
    $prestamo->update([
        'estado' => 'devuelto',
        'fecha_devolucion_real' => now()->toDateString(),
    ]);

    // It should be searchable again
    Livewire::test('pages::ti.prestamos.crear')
        ->set('search_articulo', 'Asus')
        ->assertCount('sugerencias', 1)
        ->assertSet('sugerencias.0.id', $articulo->id);

    // And we should be able to register it
    Livewire::test('pages::ti.prestamos.crear')
        ->set('user_id', $docente->id)
        ->set('search_articulo', 'Asus')
        ->call('seleccionarArticulo', $articulo->id)
        ->set('cantidad', 1)
        ->set('fecha_prestamo', now()->toDateString())
        ->set('fecha_devolucion_estimada', now()->addWeek()->toDateString())
        ->call('guardar')
        ->assertHasNoErrors();
});
