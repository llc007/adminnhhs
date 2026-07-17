<?php

use App\Models\Entrevista;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Livewire\Livewire;

test('google login with student email assigns estudiante role and links profile', function () {
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cursoId = DB::table('cursos')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'modalidad' => 'media',
        'nivel' => 1,
        'letra' => 'A',
        'nombre_fc' => '1°MA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create a pre-existing student record matching the Google email
    $estudiante = Estudiante::create([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'email' => 'juan.perez@newheavenhs.cl',
        'nombres_csv' => 'JUAN PEREZ',
        'rut_numero' => '12345678',
        'rut_dv' => '9',
    ]);

    // Mock Socialite
    $googleUser = Mockery::mock(Laravel\Socialite\Two\User::class);
    $googleUser->shouldReceive('getEmail')->andReturn('juan.perez@newheavenhs.cl');
    $googleUser->shouldReceive('getName')->andReturn('Juan Perez');
    $googleUser->shouldReceive('getId')->andReturn('google-id-student-123');
    $googleUser->shouldReceive('getAvatar')->andReturn('https://avatar.url/student');

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($provider);

    // Call Google Callback Route
    $response = $this->get(route('auth.google.callback'));

    // Check redirection to intended or dashboard
    $response->assertRedirect('/dashboard');

    // Assert user created
    $user = User::where('email', 'juan.perez@newheavenhs.cl')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('estudiante'))->toBeTrue();
    expect($user->hasRole('externo'))->toBeFalse();

    // Assert Student profile linked
    $estudiante->refresh();
    expect($estudiante->user_id)->toBe($user->id);
    expect($estudiante->vinculado_en)->not->toBeNull();
});

test('google login with non-student email assigns default externo role', function () {
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Mock Socialite with official email (no dots in local part)
    $googleUser = Mockery::mock(Laravel\Socialite\Two\User::class);
    $googleUser->shouldReceive('getEmail')->andReturn('director@newheavenhs.cl');
    $googleUser->shouldReceive('getName')->andReturn('Director NHHS');
    $googleUser->shouldReceive('getId')->andReturn('google-id-official-123');
    $googleUser->shouldReceive('getAvatar')->andReturn('https://avatar.url/director');

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'director@newheavenhs.cl')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('externo'))->toBeTrue();
    expect($user->hasRole('estudiante'))->toBeFalse();
});

test('dashboard page redirects student users to index route of entrevistas', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['estudiante']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('entrevistas.index'));
});

test('interviews index page scopes records to the authenticated student', function () {
    $userStudent1 = User::factory()->create(['email' => 'student1@newheavenhs.cl']);
    $userStudent2 = User::factory()->create(['email' => 'student2@newheavenhs.cl']);
    $userDocente = User::factory()->create(['email' => 'docente@newheavenhs.cl']);

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cursoId = DB::table('cursos')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'modalidad' => 'media',
        'nivel' => 1,
        'letra' => 'A',
        'nombre_fc' => '1°MA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $userStudent1->update(['current_school_id' => $schoolId]);
    $userStudent1->syncRolesForSchool($schoolId, ['estudiante']);

    $userStudent2->update(['current_school_id' => $schoolId]);
    $userStudent2->syncRolesForSchool($schoolId, ['estudiante']);

    // Link students to their Estudiante records
    $student1 = Estudiante::create([
        'user_id' => $userStudent1->id,
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'email' => 'student1@newheavenhs.cl',
        'nombres_csv' => 'STUDENT ONE',
        'rut_numero' => '11111111',
        'rut_dv' => '1',
    ]);

    $student2 = Estudiante::create([
        'user_id' => $userStudent2->id,
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'email' => 'student2@newheavenhs.cl',
        'nombres_csv' => 'STUDENT TWO',
        'rut_numero' => '22222222',
        'rut_dv' => '2',
    ]);

    // Create interviews
    $interviewForStudent1 = Entrevista::create([
        'school_id' => $schoolId,
        'estudiante_id' => $student1->id,
        'user_id' => $userDocente->id,
        'fecha' => now()->toDateString(),
        'hora' => '10:00:00',
        'motivo' => 'Consulta Académica',
        'estado' => 'pendiente',
    ]);

    $interviewForStudent2 = Entrevista::create([
        'school_id' => $schoolId,
        'estudiante_id' => $student2->id,
        'user_id' => $userDocente->id,
        'fecha' => now()->toDateString(),
        'hora' => '11:00:00',
        'motivo' => 'Problemas disciplinarios',
        'estado' => 'pendiente',
    ]);

    // Test as Student 1: should only see interview 1
    Livewire::actingAs($userStudent1)
        ->test('pages::entrevistas.index')
        ->assertSee('Consulta Académica')
        ->assertDontSee('Problemas disciplinarios');

    // Test as Student 2: should only see interview 2
    Livewire::actingAs($userStudent2)
        ->test('pages::entrevistas.index')
        ->assertSee('Problemas disciplinarios')
        ->assertDontSee('Consulta Académica');
});

test('student role cannot access restricted interview routes', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['estudiante']);

    $this->actingAs($user);

    $this->get(route('entrevistas.agenda'))->assertStatus(403);
    $this->get(route('entrevistas.crear'))->assertStatus(403);
});

test('student sidebar displays Mi Historial link', function () {
    $user = User::factory()->create();

    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'New Heaven High School',
        'domain' => 'newheavenhs.cl',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->update(['current_school_id' => $schoolId]);
    $user->syncRolesForSchool($schoolId, ['estudiante']);

    $response = $this->actingAs($user)->get(route('entrevistas.index'));
    $response->assertSee('Mi Historial');
    $response->assertDontSee('Historial General');
});
