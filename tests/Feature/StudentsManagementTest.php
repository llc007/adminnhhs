<?php

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function setupTestAdmin()
{
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test School',
        'domain' => 'test.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => '2026',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cursoId = DB::table('cursos')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'nivel' => 1,
        'modalidad' => 'media',
        'letra' => 'A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['administrador']);

    return [$user, $schoolId, $cursoId];
}

test('can create a student with email and it links to existing user', function () {
    [$user, $schoolId, $cursoId] = setupTestAdmin();
    $this->actingAs($user);

    // Create a student user account beforehand to test auto-linking
    $studentUser = User::factory()->create([
        'email' => 'student@test.com',
    ]);

    Livewire::test('pages::usuarios.estudiantes.index')
        ->set('nombres', 'JANE DOE')
        ->set('formCursoId', $cursoId)
        ->set('email', 'student@test.com')
        ->call('guardar');

    $this->assertDatabaseHas('estudiantes', [
        'nombres_csv' => 'JANE DOE',
        'email' => 'student@test.com',
        'user_id' => $studentUser->id,
    ]);
});

test('can edit student email in profile page and it updates user linkage', function () {
    [$user, $schoolId, $cursoId] = setupTestAdmin();
    $this->actingAs($user);

    $student = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'JANE DOE',
        'email' => 'old@test.com',
    ]);

    // Create new user for the new email to test linking update
    $newStudentUser = User::factory()->create([
        'email' => 'new@test.com',
    ]);

    Livewire::test('pages::usuarios.estudiantes.ficha', ['id' => $student->id])
        ->set('emailInstitucional', 'new@test.com')
        ->call('guardar');

    $student->refresh();
    expect($student->email)->toBe('new@test.com');
    expect($student->user_id)->toBe($newStudentUser->id);
});

test('estudiante initials and avatar helpers work correctly', function () {
    [$user, $schoolId, $cursoId] = setupTestAdmin();

    // 1. Without linked user
    $studentWithoutUser = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'LÓPEZ PÉREZ LUIS FELIPE',
    ]);

    expect($studentWithoutUser->initials())->toBe('LP');
    expect($studentWithoutUser->avatar)->toBeNull();

    // 2. With linked user
    $linkedUser = User::factory()->create([
        'nombres' => 'Luis Felipe',
        'apellido_pat' => 'López',
        'avatar' => 'https://lh3.googleusercontent.com/a/avatar-url',
    ]);

    $studentWithUser = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'LÓPEZ LUIS',
        'user_id' => $linkedUser->id,
    ]);

    expect($studentWithUser->initials())->toBe('LF');
    expect($studentWithUser->avatar)->toBe('https://lh3.googleusercontent.com/a/avatar-url');
});
