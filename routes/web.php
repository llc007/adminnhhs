<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Rutas de admin usuario
// INDEX FUNCIONARIOS
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/funcionarios', 'pages::usuarios.funcionarios.index')->name('funcionarios.index');
    Route::livewire('/funcionarios/ficha/{id}', 'pages::usuarios.funcionarios.ficha')->name('funcionarios.ficha');
    Route::livewire('/estudiantes', 'pages::usuarios.estudiantes.index')->name('estudiantes.index');
    Route::livewire('/estudiantes/ficha/{id}', 'pages::usuarios.estudiantes.ficha')->name('estudiantes.ficha');
    Route::livewire('/estudiantes/carga-masiva', 'pages::usuarios.estudiantes.carga_masiva')->name('estudiantes.carga_masiva');
    
    // ENTREVISTAS
    Route::livewire('/entrevistas/crear', 'pages::entrevistas.crear')->name('entrevistas.crear');
});
Route::view('/plantilla', 'pages.plantilla1')->name('plantilla');

require __DIR__.'/settings.php';
