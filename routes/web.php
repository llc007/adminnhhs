<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// Dashboard Analítico — Solo Administradores y Directivos
Route::middleware(['auth', 'verified', 'role:administrador,directivo,superadmin'])->group(function () {
    Route::livewire('dashboard', 'pages::entrevistas.dashboard')->name('dashboard');
    Route::livewire('/entrevistas/dashboard', 'pages::entrevistas.dashboard')->name('entrevistas.dashboard');
});

// Recepción / Portería — Recepción, Inspectores, Administradores, Directivos
Route::middleware(['auth', 'verified', 'role:recepcion,inspector,administrador,directivo,superadmin'])->group(function () {
    Route::livewire('/entrevistas/recepcion', 'pages::entrevistas.recepcion')->name('entrevistas.recepcion');
});

// Agenda, Historial General y Agendar Entrevista — Docentes, Inspectores, Administradores, Directivos
Route::middleware(['auth', 'verified', 'role:docente,inspector,administrador,directivo,superadmin'])->group(function () {
    Route::livewire('/entrevistas', 'pages::entrevistas.index')->name('entrevistas.index');
    Route::livewire('/entrevistas/agenda', 'pages::entrevistas.agenda')->name('entrevistas.agenda');
    Route::livewire('/entrevistas/crear', 'pages::entrevistas.crear')->name('entrevistas.crear');
});

// Bitácora — protegida por Policy (cualquier autenticado puede intentar, la policy decide)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/entrevistas/{entrevista}/bitacora', 'pages::entrevistas.bitacora')
        ->name('entrevistas.bitacora')
        ->middleware('can:update,entrevista');
});

// Vistas de Estudiantes — Todo el staff
Route::middleware(['auth', 'verified', 'role:directivo,administrador,superadmin,inspector,docente,asistente,psicosocial,recepcion'])->group(function () {
    Route::livewire('/estudiantes', 'pages::usuarios.estudiantes.index')->name('estudiantes.index');
    Route::livewire('/estudiantes/ficha/{id}', 'pages::usuarios.estudiantes.ficha')->name('estudiantes.ficha');
});

// Alta Administración — Gestión de Funcionarios y Cargas Masivas
Route::middleware(['auth', 'verified', 'role:directivo,administrador,superadmin'])->group(function () {
    Route::livewire('/funcionarios', 'pages::usuarios.funcionarios.index')->name('funcionarios.index');
    Route::livewire('/funcionarios/carga-masiva', 'pages::usuarios.funcionarios.carga_masiva')->name('funcionarios.carga_masiva');
    Route::livewire('/funcionarios/ficha/{id}', 'pages::usuarios.funcionarios.ficha')->name('funcionarios.ficha');
    Route::livewire('/estudiantes/carga-masiva', 'pages::usuarios.estudiantes.carga_masiva')->name('estudiantes.carga_masiva');
    Route::livewire('/estudiantes/match', 'pages::usuarios.estudiantes.match')->name('estudiantes.match');
});

Route::view('/plantilla', 'pages.plantilla1')->name('plantilla');

require __DIR__.'/settings.php';
