<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Rutas del sistema con autenticacion mid
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/citas/bitacora-entrevista', 'pages::citas.bitacora-entrevista');
});

require __DIR__.'/settings.php';
