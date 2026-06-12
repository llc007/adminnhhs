<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\MailWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::post('webhooks/mail', [MailWebhookController::class, 'handle'])->name('webhooks.mail');

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

    Route::livewire('/sin-permiso', 'pages::auth.sin-permiso')->name('sin-permiso');
});

// Vistas de Estudiantes — Todo el staff
Route::middleware(['auth', 'verified', 'role:directivo,administrador,superadmin,inspector,docente,asistente,psicosocial,recepcion'])->group(function () {
    Route::livewire('/estudiantes', 'pages::usuarios.estudiantes.index')->name('estudiantes.index');
    Route::livewire('/estudiantes/ficha/{id}', 'pages::usuarios.estudiantes.ficha')->name('estudiantes.ficha');
});

// Alta Administración — Gestión de Funcionarios y Cargas Masivas
Route::middleware(['auth', 'verified', 'role:directivo,administrador,superadmin'])->group(function () {
    Route::livewire('/funcionarios', 'pages::usuarios.funcionarios.index')->name('funcionarios.index');
    Route::livewire('/funcionarios/calculadora-horas', 'pages::usuarios.funcionarios.calculadora_horas')->name('funcionarios.calculadora_horas');
    Route::livewire('/funcionarios/carga-masiva', 'pages::usuarios.funcionarios.carga_masiva')->name('funcionarios.carga_masiva');
    Route::livewire('/funcionarios/ficha/{id}', 'pages::usuarios.funcionarios.ficha')->name('funcionarios.ficha');
    Route::livewire('/estudiantes/carga-masiva', 'pages::usuarios.estudiantes.carga_masiva')->name('estudiantes.carga_masiva');
    Route::livewire('/estudiantes/match', 'pages::usuarios.estudiantes.match')->name('estudiantes.match');
    Route::livewire('/estudiantes/agregar-rut', 'pages::usuarios.estudiantes.agregar-rut')->name('estudiantes.agregar_rut');
    Route::livewire('/admin/historial-correos', 'pages::admin.mail_logs')->name('admin.mail_logs');
});

Route::get('/office', function () {
    return redirect()->away('https://drive.google.com/file/d/1i8T9g1mlSsUj4xhGGC6-Y99Fwe30fMy7/view?usp=sharing');
})->name('office');

// Módulo de Adquisiciones e Inventario — Fase 1
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/adquisiciones/crear', 'pages::adquisiciones.crear')->name('adquisiciones.crear');
});

Route::middleware(['auth', 'verified', 'role:directivo,administrador,superadmin'])->group(function () {
    Route::livewire('/adquisiciones/revision', 'pages::adquisiciones.revision')->name('adquisiciones.revision');
    Route::livewire('/adquisiciones/compras', 'pages::adquisiciones.compras')->name('adquisiciones.compras');
    Route::livewire('/inventario', 'pages::inventario.index')->name('inventario.index');
});

Route::view('/plantilla', 'pages.plantilla1')->name('plantilla');

require __DIR__.'/settings.php';
