<?php

use App\Models\ArticuloInventario;
use App\Models\InventarioCategoria;
use App\Models\InventarioUbicacion;
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

    $cat = InventarioCategoria::create([
        'school_id' => $schoolId,
        'nombre' => 'Mobiliario',
    ]);
    $ub = InventarioUbicacion::create([
        'school_id' => $schoolId,
        'nombre' => 'Bodega A',
    ]);
    $ubNew = InventarioUbicacion::create([
        'school_id' => $schoolId,
        'nombre' => 'Rectoría',
    ]);

    // Direct add
    Livewire::test('pages::inventario.index')
        ->set('nuevoTipo', 'activo')
        ->set('nuevoNombre', 'Silla de Oficina Giratoria')
        ->set('nuevaCategoriaId', $cat->id)
        ->set('nuevoCodigo', 'MOB-SIL-050')
        ->set('nuevoEstado', 'excelente')
        ->set('nuevaUbicacionId', $ub->id)
        ->call('guardarAltaDirecta')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('articulo_inventarios', [
        'codigo_patrimonial' => 'MOB-SIL-001',
        'nombre' => 'Silla de Oficina Giratoria',
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega A',
        'categoria_id' => $cat->id,
        'ubicacion_id' => $ub->id,
    ]);

    $articulo = ArticuloInventario::where('codigo_patrimonial', 'MOB-SIL-001')->first();

    // Quick edit custodian and state using the new details page (auto-saved)
    Livewire::test('pages::inventario.detalles', ['id' => $articulo->id])
        ->set('editingItems.item_'.$articulo->id.'.responsable_user_id', $user->id)
        ->set('editingItems.item_'.$articulo->id.'.estado_conservacion', 'bueno')
        ->assertHasNoErrors()
        // Edit physical details
        ->call('abrirFisicos', $articulo->id)
        ->set('editUbicacionId', $ubNew->id)
        ->call('guardarFisicos')
        ->assertHasNoErrors();

    $articulo->refresh();
    expect($articulo->responsable_user_id)->toBe($user->id);
    expect($articulo->ubicacion)->toBe('Rectoría');
    expect($articulo->estado_conservacion)->toBe('bueno');
});

test('ti admin can add multiple assets directly with auto consecutive correlatives', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $cat = InventarioCategoria::create([
        'school_id' => $schoolId,
        'nombre' => 'Tecnología',
    ]);
    $ub = InventarioUbicacion::create([
        'school_id' => $schoolId,
        'nombre' => 'Laboratorio B',
    ]);

    Livewire::test('pages::inventario.index')
        ->set('nuevoTipo', 'activo')
        ->set('nuevoNombre', 'Computador iMac')
        ->set('nuevaCategoriaId', $cat->id)
        ->set('nuevaCantidad', 3)
        ->set('nuevoEstado', 'excelente')
        ->set('nuevaUbicacionId', $ub->id)
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

test('ti admin can log maintenance and decommission an asset', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $cat = InventarioCategoria::create(['school_id' => $schoolId, 'nombre' => 'Mobiliario']);
    $ub = InventarioUbicacion::create(['school_id' => $schoolId, 'nombre' => 'Bodega']);

    $articulo = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'MOB-SIL-999',
        'nombre' => 'Silla Ejecutiva',
        'categoria' => 'Mobiliario',
        'categoria_id' => $cat->id,
        'cantidad' => 1,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega',
        'ubicacion_id' => $ub->id,
        'fecha_ingreso' => now(),
    ]);

    // Log a maintenance/revision
    Livewire::test('pages::inventario.detalles', ['id' => $articulo->id])
        ->call('abrirRevisiones', $articulo->id)
        ->set('nuevaRevFecha', now()->toDateString())
        ->set('nuevaRevDetalle', 'Ajuste de pistón de altura')
        ->set('nuevaRevRealizadoPor', 'Servicio Técnico Interno')
        ->set('nuevaRevProximaFecha', now()->addMonths(6)->toDateString())
        ->call('guardarRevision')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('revisiones_inventario', [
        'articulo_inventario_id' => $articulo->id,
        'detalle' => 'Ajuste de pistón de altura',
        'realizado_por' => 'Servicio Técnico Interno',
    ]);

    // Decommission the item
    Livewire::test('pages::inventario.detalles', ['id' => $articulo->id])
        ->call('abrirBaja', $articulo->id)
        ->set('bajaFecha', now()->toDateString())
        ->set('bajaMotivo', 'Pistón roto irreparable')
        ->call('confirmarBaja')
        ->assertHasNoErrors();

    $articulo->refresh();
    expect($articulo->fecha_baja)->not->toBeNull();
    expect($articulo->motivo_baja)->toBe('Pistón roto irreparable');
    expect($articulo->responsable_user_id)->toBeNull();

    // Verify it is excluded from search suggestions in loans
    Livewire::test('pages::ti.prestamos.crear')
        ->set('search_articulo', 'Silla Ejecutiva')
        ->assertSet('sugerencias', []);
});

test('ti admin can view and discount stock of a consumable with audit logs', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $consumible = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'consumible',
        'codigo_patrimonial' => 'BAR-CAB-001',
        'nombre' => 'Cable Eléctrico 10m',
        'categoria' => 'Eléctrico',
        'cantidad' => 50,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega Central',
        'fecha_ingreso' => now(),
    ]);

    Livewire::test('pages::inventario.detalles', ['id' => $consumible->id])
        ->assertSee('Cable Eléctrico 10m')
        // Open descontar modal
        ->call('abrirDescontar', $consumible->id)
        ->set('descontarCantidad', 5)
        ->set('descontarMotivo', 'Instalación sala 4')
        ->call('confirmarDescontar')
        ->assertHasNoErrors()
        ->assertSet('modalDescontar', false);

    $consumible->refresh();
    expect($consumible->cantidad)->toBe(45);

    // Verify it is logged in revisiones_inventario
    $this->assertDatabaseHas('revisiones_inventario', [
        'articulo_inventario_id' => $consumible->id,
        'detalle' => 'Consumo de stock: -5 unidades. Motivo: Instalación sala 4. Stock restante: 45.',
        'realizado_por' => $user->nombreCompleto(),
    ]);
});

test('ti admin can dynamically create categories subcategories and locations inline', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    // Dynamic category/location creation in index.blade.php
    Livewire::test('pages::inventario.index')
        ->set('searchCategoria', 'Tecnología Electrónica')
        ->call('crearCategoria')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('inventario_categorias', [
        'school_id' => $schoolId,
        'nombre' => 'Tecnología Electrónica',
    ]);

    $cat = InventarioCategoria::where('nombre', 'Tecnología Electrónica')->first();

    Livewire::test('pages::inventario.index')
        ->set('nuevaCategoriaId', $cat->id)
        ->set('searchSubcategoria', 'Cables HDMI')
        ->call('crearSubcategoria')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('inventario_subcategorias', [
        'school_id' => $schoolId,
        'categoria_id' => $cat->id,
        'nombre' => 'Cables HDMI',
    ]);

    Livewire::test('pages::inventario.index')
        ->set('searchUbicacion', 'Sala de Robótica')
        ->call('crearUbicacion')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('inventario_ubicaciones', [
        'school_id' => $schoolId,
        'nombre' => 'Sala de Robótica',
    ]);
});

test('ti admin can delete an entire article group/lote', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $cat = InventarioCategoria::create(['school_id' => $schoolId, 'nombre' => 'Tecnología']);
    $ub = InventarioUbicacion::create(['school_id' => $schoolId, 'nombre' => 'Bodega']);

    ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'TEC-PRO-001',
        'nombre' => 'Proyector Epson',
        'categoria' => 'Tecnología',
        'categoria_id' => $cat->id,
        'cantidad' => 1,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega',
        'ubicacion_id' => $ub->id,
        'fecha_ingreso' => now(),
    ]);

    ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'TEC-PRO-002',
        'nombre' => 'Proyector Epson',
        'categoria' => 'Tecnología',
        'categoria_id' => $cat->id,
        'cantidad' => 1,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega',
        'ubicacion_id' => $ub->id,
        'fecha_ingreso' => now(),
    ]);

    $this->assertEquals(2, ArticuloInventario::where('nombre', 'Proyector Epson')->count());

    Livewire::test('pages::inventario.index')
        ->call('mostrarModalEliminar', 'Proyector Epson', 'Tecnología', '', '', 'activo', now()->toDateString())
        ->call('confirmarEliminar')
        ->assertHasNoErrors();

    $this->assertEquals(0, ArticuloInventario::where('nombre', 'Proyector Epson')->count());
});

test('ti admin can edit article details across all items in a group', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $cat = InventarioCategoria::create(['school_id' => $schoolId, 'nombre' => 'Tecnología']);
    $catNew = InventarioCategoria::create(['school_id' => $schoolId, 'nombre' => 'Mobiliario']);
    $ub = InventarioUbicacion::create(['school_id' => $schoolId, 'nombre' => 'Bodega']);

    $art1 = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'TEC-ESC-001',
        'nombre' => 'Escritorio Madera',
        'categoria' => 'Tecnología',
        'categoria_id' => $cat->id,
        'cantidad' => 1,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega',
        'ubicacion_id' => $ub->id,
        'fecha_ingreso' => now(),
    ]);

    $art2 = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'TEC-ESC-002',
        'nombre' => 'Escritorio Madera',
        'categoria' => 'Tecnología',
        'categoria_id' => $cat->id,
        'cantidad' => 1,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega',
        'ubicacion_id' => $ub->id,
        'fecha_ingreso' => now(),
    ]);

    Livewire::test('pages::inventario.detalles', ['id' => $art1->id])
        ->call('abrirEditarArticulo')
        ->set('editNombre', 'Escritorio de Madera Premium')
        ->set('editCategoriaId', $catNew->id)
        ->set('editMarca', 'Alinea')
        ->set('editModelo', 'M1')
        ->call('guardarEdicionArticulo')
        ->assertHasNoErrors();

    $art1->refresh();
    $art2->refresh();

    expect($art1->nombre)->toBe('Escritorio de Madera Premium');
    expect($art1->categoria)->toBe('Mobiliario');
    expect($art1->marca)->toBe('Alinea');
    expect($art1->modelo)->toBe('M1');

    expect($art2->nombre)->toBe('Escritorio de Madera Premium');
    expect($art2->categoria)->toBe('Mobiliario');
    expect($art2->marca)->toBe('Alinea');
    expect($art2->modelo)->toBe('M1');
});

test('ti admin can add stock to a consumable in details', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $consumible = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'consumible',
        'codigo_patrimonial' => 'BAR-CAB-001',
        'nombre' => 'Cable Eléctrico 10m',
        'categoria' => 'Eléctrico',
        'cantidad' => 50,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega Central',
        'fecha_ingreso' => now(),
    ]);

    Livewire::test('pages::inventario.detalles', ['id' => $consumible->id])
        ->call('abrirAgregarStock', $consumible->id)
        ->set('agregarStockCantidad', 20)
        ->set('agregarStockMotivo', 'Compra de reposición')
        ->call('confirmarAgregarStock')
        ->assertHasNoErrors();

    $consumible->refresh();
    expect($consumible->cantidad)->toBe(70);

    $this->assertDatabaseHas('revisiones_inventario', [
        'articulo_inventario_id' => $consumible->id,
        'detalle' => 'Ingreso de stock: +20 unidades. Motivo: Compra de reposición. Nuevo stock: 70.',
    ]);
});

test('ti admin can add units to an active asset in details', function () {
    [$user, $schoolId] = setupInventarioTestUser(['administrador']);
    $this->actingAs($user);

    $cat = InventarioCategoria::create(['school_id' => $schoolId, 'nombre' => 'Tecnología']);
    $ub = InventarioUbicacion::create(['school_id' => $schoolId, 'nombre' => 'Bodega']);

    $articulo = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'TEC-PRO-001',
        'nombre' => 'Proyector Epson',
        'categoria' => 'Tecnología',
        'categoria_id' => $cat->id,
        'cantidad' => 1,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Bodega',
        'ubicacion_id' => $ub->id,
        'fecha_ingreso' => now(),
    ]);

    Livewire::test('pages::inventario.detalles', ['id' => $articulo->id])
        ->call('abrirAgregarUnidades')
        ->set('agregarUnidadesCantidad', 2)
        ->set('agregarUnidadesUbicacionId', $ub->id)
        ->set('agregarUnidadesEstado', 'excelente')
        ->call('confirmarAgregarUnidades')
        ->assertHasNoErrors();

    // The total count of Epson Projectors with type = 'activo' should now be 3
    $this->assertEquals(3, ArticuloInventario::where('nombre', 'Proyector Epson')->where('tipo', 'activo')->count());

    // Should have generated codes TEC-PRO-002 and TEC-PRO-003
    $this->assertDatabaseHas('articulo_inventarios', [
        'codigo_patrimonial' => 'TEC-PRO-002',
        'nombre' => 'Proyector Epson',
    ]);
    $this->assertDatabaseHas('articulo_inventarios', [
        'codigo_patrimonial' => 'TEC-PRO-003',
        'nombre' => 'Proyector Epson',
    ]);
});
