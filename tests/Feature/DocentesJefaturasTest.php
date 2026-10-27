<?php

use App\Enums\Modalidad;
use App\Models\AcademicYear;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function setupSchoolAndStaff(): array
{
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Colegio Test NHHS',
        'domain' => 'test-school.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $admin = User::factory()->create([
        'current_school_id' => $schoolId,
        'email' => 'admin@test-school.cl',
    ]);
    $admin->syncRolesForSchool($schoolId, ['administrador']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
    $admin->givePermissionTo(Permission::findOrCreate('gestionar-funcionarios', 'web'));

    $year = AcademicYear::create([
        'school_id' => $schoolId,
        'name' => 'Año 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $cursoA = Curso::create([
        'school_id' => $schoolId,
        'academic_year_id' => $year->id,
        'modalidad' => Modalidad::Basica,
        'nivel' => 3,
        'letra' => 'A',
        'nombre_fc' => '3 BASICO A',
    ]);

    $cursoB = Curso::create([
        'school_id' => $schoolId,
        'academic_year_id' => $year->id,
        'modalidad' => Modalidad::Media,
        'nivel' => 2,
        'letra' => 'B',
        'nombre_fc' => '2 MEDIO B',
    ]);

    $docente1 = User::factory()->create([
        'current_school_id' => $schoolId,
        'nombres' => 'MARIA',
        'apellido_pat' => 'PARRA',
        'email' => 'maria.parra@test-school.cl',
    ]);
    $docente1->syncRolesForSchool($schoolId, ['docente']);

    $docente2 = User::factory()->create([
        'current_school_id' => $schoolId,
        'nombres' => 'CARLOS',
        'apellido_pat' => 'FUENTES',
        'email' => 'carlos.fuentes@test-school.cl',
    ]);
    $docente2->syncRolesForSchool($schoolId, ['docente']);

    return [$schoolId, $admin, $year, $cursoA, $cursoB, $docente1, $docente2];
}

test('unauthenticated users are redirected to login when accessing docentes', function () {
    $this->get(route('docentes.index'))
        ->assertRedirect(route('login'));
});

test('administrators can access the docentes and jefaturas page', function () {
    [, $admin] = setupSchoolAndStaff();

    $this->actingAs($admin)
        ->get(route('docentes.index'))
        ->assertOk()
        ->assertSee('Gestión de Docentes y Jefaturas');
});

test('component lists teachers and shows their jefatura status', function () {
    [$schoolId, $admin, , $cursoA, , $docente1, $docente2] = setupSchoolAndStaff();

    // Assign cursoA to docente1
    $cursoA->update(['jefe_id' => $docente1->id]);

    $this->actingAs($admin);

    Livewire::test('pages::usuarios.docentes.index')
        ->assertSee('MARIA PARRA')
        ->assertSee('CARLOS FUENTES')
        ->assertSee($cursoA->nombreCompleto())
        ->assertSee('Sin jefatura');
});

test('an administrator can assign a course jefatura to a teacher', function () {
    [, $admin, , $cursoA, , $docente1] = setupSchoolAndStaff();

    $this->actingAs($admin);

    Livewire::test('pages::usuarios.docentes.index')
        ->call('abrirModalDocente', $docente1->id)
        ->assertSet('selectedDocenteId', $docente1->id)
        ->set('selectedCursoId', (string) $cursoA->id)
        ->call('guardarJefatura')
        ->assertSet('modalJefatura', false);

    $cursoA->refresh();
    expect($cursoA->jefe_id)->toBe($docente1->id);
    expect($docente1->cursoJefatura())->not->toBeNull();
    expect($docente1->cursoJefatura()->id)->toBe($cursoA->id);
});

test('an administrator can reassign a teacher to Sin jefatura', function () {
    [, $admin, , $cursoA, , $docente1] = setupSchoolAndStaff();

    $cursoA->update(['jefe_id' => $docente1->id]);

    $this->actingAs($admin);

    Livewire::test('pages::usuarios.docentes.index')
        ->call('abrirModalDocente', $docente1->id)
        ->set('selectedCursoId', 'none')
        ->call('guardarJefatura');

    $cursoA->refresh();
    expect($cursoA->jefe_id)->toBeNull();
    expect($docente1->cursoJefatura())->toBeNull();
});

test('quitarJefatura removes course assignment immediately', function () {
    [, $admin, , $cursoA, , $docente1] = setupSchoolAndStaff();

    $cursoA->update(['jefe_id' => $docente1->id]);

    $this->actingAs($admin);

    Livewire::test('pages::usuarios.docentes.index')
        ->call('quitarJefatura', $docente1->id);

    $cursoA->refresh();
    expect($cursoA->jefe_id)->toBeNull();
});

test('assigning a new course automatically frees previous course held by same teacher', function () {
    [, $admin, , $cursoA, $cursoB, $docente1] = setupSchoolAndStaff();

    $cursoA->update(['jefe_id' => $docente1->id]);

    $this->actingAs($admin);

    // Assign cursoB to docente1
    Livewire::test('pages::usuarios.docentes.index')
        ->call('abrirModalDocente', $docente1->id)
        ->set('selectedCursoId', (string) $cursoB->id)
        ->call('guardarJefatura');

    $cursoA->refresh();
    $cursoB->refresh();

    expect($cursoA->jefe_id)->toBeNull();
    expect($cursoB->jefe_id)->toBe($docente1->id);
});

test('guardarJefeCurso assigns teacher to course from courses tab modal', function () {
    [, $admin, , $cursoA, , $docente2] = setupSchoolAndStaff();

    $this->actingAs($admin);

    Livewire::test('pages::usuarios.docentes.index')
        ->call('abrirModalCurso', $cursoA->id)
        ->set('selectedDocenteForModalId', (string) $docente2->id)
        ->call('guardarJefeCurso')
        ->assertSet('modalCursoJefe', false);

    $cursoA->refresh();
    expect($cursoA->jefe_id)->toBe($docente2->id);
});
