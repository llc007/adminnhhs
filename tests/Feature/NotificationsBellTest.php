<?php

use App\Models\Curso;
use App\Models\Entrevista;
use App\Models\User;
use App\Notifications\IngresoApoderado;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('notifications bell component renders, handles anti-spam on load, and toasts new arrivals', function () {
    // 1. Create dependencies directly using exact database schema
    $user = User::factory()->create();

    // Insert School
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Test High School',
        'domain' => 'test-high-school.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Update user current school ID
    $user->update([
        'current_school_id' => $schoolId,
    ]);

    // Insert Academic Year
    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Academic Year 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create a Curso
    $cursoId = DB::table('cursos')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'nivel' => 1,
        'modalidad' => 'media',
        'letra' => 'A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create a Student
    $studentId = DB::table('estudiantes')->insertGetId([
        'school_id' => $schoolId,
        'curso_id' => $cursoId,
        'nombres_csv' => 'Jane Doe Smith',
        'rut_numero' => '12345678',
        'rut_dv' => '9',
        'apoderado_nombres' => 'John',
        'apoderado_apellido_pat' => 'Doe',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create an Interview
    $entrevista = Entrevista::create([
        'school_id' => $schoolId,
        'user_id' => $user->id,
        'estudiante_id' => $studentId,
        'fecha' => now()->format('Y-m-d'),
        'hora' => '10:00:00',
        'motivo' => 'Reunión de Apoderados',
        'urgencia' => 'normal',
        'estado' => 'pendiente',
    ]);

    // 2. Pre-notify user prior to mount
    $user->notify(new IngresoApoderado($entrevista));
    $user->refresh();
    expect($user->unreadNotifications->count())->toBe(1);

    // 3. Mount the component and verify the notification is mapped to notifiedIds (anti-spam check)
    $component = Livewire::actingAs($user)->test('layout.notifications-bell');

    $initialNotifId = $user->unreadNotifications->first()->id;
    expect($component->get('notifiedIds'))->toContain($initialNotifId);

    // 4. Send a brand new notification (simulating real-time check-in)
    $user->notify(new IngresoApoderado($entrevista));
    $user->refresh();
    expect($user->unreadNotifications->count())->toBe(2);

    // 5. Trigger a component render update/refresh (calls the checkNewNotifications action)
    $component->call('checkNewNotifications');

    // 6. Assert that the second notification has been registered in notifiedIds
    $unreadNotifications = $user->unreadNotifications;
    $newNotifId = $unreadNotifications->firstWhere('id', '!=', $initialNotifId)->id;

    expect($component->get('notifiedIds'))->toContain($newNotifId);
});
