<?php

use App\Models\ArticuloInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('directivo role cannot access general inventory, revision, compras, and admin pages', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['directivo'])]);

    $this->actingAs($user);

    // Forbidden routes for directivo
    $this->get(route('inventario.index'))->assertStatus(403);
    $this->get(route('inventario.detalles', ['id' => 1]))->assertStatus(403);
    $this->get(route('adquisiciones.revision'))->assertStatus(403);
    $this->get(route('adquisiciones.compras'))->assertStatus(403);
    $this->get(route('admin.modules'))->assertStatus(403);
    $this->get(route('admin.mail_logs'))->assertStatus(403);
    $this->get(route('funcionarios.calculadora_horas'))->assertStatus(403);

    // Allowed routes for directivo (Gestión Académica)
    $this->get(route('funcionarios.index'))->assertStatus(200);
    $this->get(route('funcionarios.ficha', ['id' => $otherUser->id]))->assertStatus(200);
});

test('users with solicitante_adquisiciones role can access create acquisitions page', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['solicitante_adquisiciones'])]);

    $this->actingAs($user);

    $this->get(route('adquisiciones.crear'))->assertStatus(200);
});

test('users without solicitante_adquisiciones role cannot access create acquisitions page', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Give them directivo role instead
    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['directivo'])]);

    $this->actingAs($user);

    $this->get(route('adquisiciones.crear'))->assertStatus(403);
});

test('administrador can access both inventory and acquisitions pages', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['administrador'])]);

    $this->actingAs($user);

    $this->get(route('adquisiciones.crear'))->assertStatus(200);
    $this->get(route('inventario.index'))->assertStatus(200);
});

test('ti role can access general inventory and loans but nothing else', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->schools()->attach($schoolId, ['roles' => json_encode(['ti'])]);

    // Create dummy article to test details access
    $articulo = ArticuloInventario::create([
        'school_id' => $schoolId,
        'tipo' => 'activo',
        'codigo_patrimonial' => 'TEC-LAP-999',
        'nombre' => 'Laptop de Test',
        'categoria' => 'Tecnología',
        'cantidad' => 1,
        'estado_conservacion' => 'excelente',
        'ubicacion' => 'Oficina TI',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $this->actingAs($user);

    // Allowed routes for ti
    $this->get(route('inventario.index'))->assertStatus(200);
    $this->get(route('inventario.detalles', ['id' => $articulo->id]))->assertStatus(200);
    $this->get(route('ti.prestamos.index'))->assertStatus(200);

    // Forbidden routes for ti
    $this->get(route('adquisiciones.crear'))->assertStatus(403);
    $this->get(route('adquisiciones.revision'))->assertStatus(403);
    $this->get(route('adquisiciones.compras'))->assertStatus(403);
    $this->get(route('admin.modules'))->assertStatus(403);
    $this->get(route('admin.mail_logs'))->assertStatus(403);
    $this->get(route('funcionarios.calculadora_horas'))->assertStatus(403);
    $this->get(route('funcionarios.index'))->assertStatus(403);
    $this->get(route('funcionarios.ficha', ['id' => $otherUser->id]))->assertStatus(403);
});
