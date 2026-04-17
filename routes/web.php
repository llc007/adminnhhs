<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// 1. Staff General (Cualquier trabajador del colegio)
Route::middleware(['auth', 'verified', 'role:directivo,administrador,superadmin,inspector,docente,asistente,psicosocial,recepcion'])->group(function () {
    // Estudiantes (vistas compartidas)
    Route::livewire('/estudiantes', 'pages::usuarios.estudiantes.index')->name('estudiantes.index');
    Route::livewire('/estudiantes/ficha/{id}', 'pages::usuarios.estudiantes.ficha')->name('estudiantes.ficha');
    
    // Entrevistas compartidas (Docentes, Profesionales)
    Route::livewire('/entrevistas/agenda', 'pages::entrevistas.agenda')->name('entrevistas.agenda');
    Route::livewire('/entrevistas/{entrevista}/bitacora', 'pages::entrevistas.bitacora')->name('entrevistas.bitacora');
});

// 2. Recepción, Inspectores y Administración (Gestión macro de citas)
Route::middleware(['auth', 'verified', 'role:directivo,administrador,superadmin,inspector,recepcion'])->group(function () {
    Route::livewire('/entrevistas', 'pages::entrevistas.index')->name('entrevistas.index');
    Route::livewire('/entrevistas/recepcion', 'pages::entrevistas.recepcion')->name('entrevistas.recepcion');
    Route::livewire('/entrevistas/crear', 'pages::entrevistas.crear')->name('entrevistas.crear');
});

// 3. Alta Administración (Asignación de perfiles, cargos y subida masiva)
Route::middleware(['auth', 'verified', 'role:directivo,administrador,superadmin'])->group(function () {
    Route::livewire('/funcionarios', 'pages::usuarios.funcionarios.index')->name('funcionarios.index');
    Route::livewire('/funcionarios/carga-masiva', 'pages::usuarios.funcionarios.carga_masiva')->name('funcionarios.carga_masiva');
    Route::livewire('/funcionarios/ficha/{id}', 'pages::usuarios.funcionarios.ficha')->name('funcionarios.ficha');
    Route::livewire('/estudiantes/carga-masiva', 'pages::usuarios.estudiantes.carga_masiva')->name('estudiantes.carga_masiva');
});
Route::view('/plantilla', 'pages.plantilla1')->name('plantilla');

require __DIR__.'/settings.php';
