<?php

use App\Models\ArticuloInventario;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function setupInventarioTestUser(array $roles = ['docente'])
{
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'testschool.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode($roles)]);

    return [$user, $schoolId];
}

test('guests are redirected to login from acquisitions pages', function () {
    $this->get(route('adquisiciones.crear'))->assertRedirect(route('login'));
    $this->get(route('adquisiciones.revision'))->assertRedirect(route('login'));
    $this->get(route('adquisiciones.compras'))->assertRedirect(route('login'));
    $this->get(route('inventario.index'))->assertRedirect(route('login'));
});

test('staff members can create an acquisitions requisition with items and observations', function () {
    [$user, $schoolId] = setupInventarioTestUser(['docente']);
    $this->actingAs($user);

    Livewire::test('pages::adquisiciones.crear')
        ->set('justificacion', 'Para el laboratorio de ciencias')
        ->set('descripcion', 'Proyector Epson L5')
        ->set('cantidad', 2)
        ->set('tienda_sugerida', 'PC Factory')
        ->set('observacion', 'Debe incluir soporte aéreo y cable HDMI largo')
        ->call('agregarItem')
        ->assertSet('items', [
            [
                'descripcion' => 'Proyector Epson L5',
                'cantidad' => 2,
                'tienda_sugerida' => 'PC Factory',
                'observacion' => 'Debe incluir soporte aéreo y cable HDMI largo',
                'precio_estimado' => 0,
            ],
        ])
        ->call('guardarRequerimiento')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('requerimientos', [
        'justificacion' => 'Para el laboratorio de ciencias',
        'estado' => 'pendiente_rectoria',
        'school_id' => $schoolId,
    ]);

    $this->assertDatabaseHas('requerimiento_items', [
        'descripcion' => 'Proyector Epson L5',
        'cantidad' => 2,
        'observacion' => 'Debe incluir soporte aéreo y cable HDMI largo',
        'estado' => 'pendiente',
    ]);
});

test('rectoria and gerencia can review and approve a requisition', function () {
    [$user, $schoolId] = setupInventarioTestUser(['directivo', 'administrador']);
    $this->actingAs($user);

    $req = Requerimiento::create([
        'user_id' => $user->id,
        'school_id' => $schoolId,
        'justificacion' => 'Para el depto de TI',
        'estado' => 'pendiente_rectoria',
    ]);

    $item = RequerimientoItem::create([
        'requerimiento_id' => $req->id,
        'descripcion' => 'Laptop HP',
        'cantidad' => 1,
        'precio_estimado' => 0,
        'estado' => 'pendiente',
    ]);

    // Rectoría reviews and approves
    Livewire::test('pages::adquisiciones.revision')
        ->set('rolRevisor', 'rectoria')
        ->call('selectRequerimiento', $req->id)
        ->set("itemStates.{$item->id}.estado", 'aprobado')
        ->call('procesarRevision')
        ->assertHasNoErrors();

    $req->refresh();
    expect($req->estado)->toBe('pendiente_gerencia');
    expect($item->refresh()->estado)->toBe('aprobado_rectoria');

    // Gerencia reviews and authorizes
    Livewire::test('pages::adquisiciones.revision')
        ->set('rolRevisor', 'gerencia')
        ->call('selectRequerimiento', $req->id)
        ->set("itemStates.{$item->id}.estado", 'aprobado')
        ->call('procesarRevision')
        ->assertHasNoErrors();

    $req->refresh();
    expect($req->estado)->toBe('en_adquisicion');
    expect($item->refresh()->estado)->toBe('aprobado_gerencia');
});

test('adquisiciones can receive and register physical serial numbers of purchased items', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $req = Requerimiento::create([
        'user_id' => $user->id,
        'school_id' => $schoolId,
        'justificacion' => 'Compra TI',
        'estado' => 'en_adquisicion',
    ]);

    $item = RequerimientoItem::create([
        'requerimiento_id' => $req->id,
        'descripcion' => 'Computador Dell Vostro',
        'cantidad' => 1,
        'precio_estimado' => 0,
        'estado' => 'aprobado_gerencia',
    ]);

    Livewire::test('pages::adquisiciones.compras')
        ->call('selectRequerimiento', $req->id)
        ->call('selectItem', $item->id)
        ->set('tipo', 'activo')
        ->set('categoria', 'Tecnología')
        ->set('marca', 'Dell')
        ->set('modelo', 'Vostro 3400')
        ->set('activosData.0.numero_serie', 'XYZ987654')
        ->set('activosData.0.codigo_patrimonial', 'TEC-COM-100')
        ->call('registrarIngreso')
        ->assertHasNoErrors();

    // Check inventory record
    $this->assertDatabaseHas('articulo_inventarios', [
        'codigo_patrimonial' => 'TEC-COM-100',
        'numero_serie' => 'XYZ987654',
        'tipo' => 'activo',
        'ubicacion' => 'Bodega Central',
        'responsable_user_id' => null,
    ]);

    // Check digital signature acta
    $this->assertDatabaseHas('acta_entregas', [
        'requerimiento_id' => $req->id,
        'recibe_user_id' => $user->id,
        'entrega_user_id' => $user->id,
        'firmado_at' => null,
    ]);

    // Check request was fully received
    $req->refresh();
    expect($req->estado)->toBe('recibido');
});

test('adquisiciones can receive and register direct expenses bypassing inventory', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $req = Requerimiento::create([
        'user_id' => $user->id,
        'school_id' => $schoolId,
        'justificacion' => 'Evento Institucional',
        'estado' => 'en_adquisicion',
    ]);

    $item = RequerimientoItem::create([
        'requerimiento_id' => $req->id,
        'descripcion' => 'Desayuno para 40 personas',
        'cantidad' => 1,
        'precio_estimado' => 0,
        'estado' => 'aprobado_gerencia',
    ]);

    Livewire::test('pages::adquisiciones.compras')
        ->call('selectRequerimiento', $req->id)
        ->call('selectItem', $item->id)
        ->set('modoIngreso', 'gasto_directo')
        ->call('registrarIngreso')
        ->assertHasNoErrors();

    // Check that NO inventory record was created
    $this->assertDatabaseMissing('articulo_inventarios', [
        'nombre' => 'Desayuno para 40 personas',
    ]);

    // Check item state updated to entregado
    $item->refresh();
    expect($item->estado)->toBe('entregado');

    // Check request was fully received
    $req->refresh();
    expect($req->estado)->toBe('recibido');
});

test('ti admin can add items directly and update custodian and state in general inventory', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    // Direct add
    Livewire::test('pages::inventario.index')
        ->set('nuevoTipo', 'activo')
        ->set('nuevoNombre', 'Silla de Oficina Giratoria')
        ->set('nuevaCategoria', 'Mobiliario')
        ->set('nuevoCodigo', 'MOB-SIL-050')
        ->set('nuevoEstado', 'excelente')
        ->set('nuevaUbicacion', 'Bodega A')
        ->call('guardarAltaDirecta')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('articulo_inventarios', [
        'codigo_patrimonial' => 'MOB-SIL-001',
        'nombre' => 'Silla de Oficina Giratoria',
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega A',
    ]);

    $articulo = ArticuloInventario::where('codigo_patrimonial', 'MOB-SIL-001')->first();

    // Quick edit custodian and state
    Livewire::test('pages::inventario.index')
        ->call('abrirEditar', $articulo->id)
        ->set('editResponsableId', $user->id)
        ->set('editUbicacion', 'Rectoría')
        ->set('editEstado', 'bueno')
        ->call('guardarEdicion')
        ->assertHasNoErrors();

    $articulo->refresh();
    expect($articulo->responsable_user_id)->toBe($user->id);
    expect($articulo->ubicacion)->toBe('Rectoría');
    expect($articulo->estado_conservacion)->toBe('bueno');
});

test('ti admin can add multiple assets directly with auto consecutive correlatives', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    Livewire::test('pages::inventario.index')
        ->set('nuevoTipo', 'activo')
        ->set('nuevoNombre', 'Computador iMac')
        ->set('nuevaCategoria', 'Tecnología')
        ->set('nuevaCantidad', 3)
        ->set('nuevoEstado', 'excelente')
        ->set('nuevaUbicacion', 'Laboratorio B')
        ->call('guardarAltaDirecta')
        ->assertHasNoErrors();

    // Check that 3 consecutive items were created
    $this->assertDatabaseHas('articulo_inventarios', [
        'codigo_patrimonial' => 'TEC-COM-001',
        'nombre' => 'Computador iMac',
        'tipo' => 'activo',
    ]);
    $this->assertDatabaseHas('articulo_inventarios', [
        'codigo_patrimonial' => 'TEC-COM-002',
        'nombre' => 'Computador iMac',
        'tipo' => 'activo',
    ]);
    $this->assertDatabaseHas('articulo_inventarios', [
        'codigo_patrimonial' => 'TEC-COM-003',
        'nombre' => 'Computador iMac',
        'tipo' => 'activo',
    ]);
});
