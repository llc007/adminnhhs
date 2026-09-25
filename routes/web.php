<?php

use App\Http\Controllers\AtrasoPrintController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\MailWebhookController;
use App\Http\Controllers\ReportesPrintController;
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

// Dashboard Dirección & Rectoría — Gerencia, Rectoría, Directivos, Administradores y Superadmin
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|administrador|directivo|gerencia|rectoria|ver-dashboard-gerencia'])->group(function () {
    Route::livewire('/direccion/dashboard', 'pages::gerencia.dashboard')->name('direccion.dashboard');
    Route::get('/direccion/imprimir/resumen-ejecutivo', [ReportesPrintController::class, 'resumenEjecutivo'])->name('direccion.imprimir.resumen');

    // Redirecciones de compatibilidad para rutas previas
    Route::get('/gerencia/dashboard', fn () => redirect()->route('direccion.dashboard'))->name('gerencia.dashboard');
    Route::get('/gerencia/imprimir/resumen-ejecutivo', fn () => redirect()->route('direccion.imprimir.resumen', request()->query()))->name('gerencia.imprimir.resumen');
    Route::redirect('/direccion', '/direccion/dashboard');
    Route::redirect('/gerencia', '/direccion/dashboard');
});

// Reportes Estadísticos — Administradores, Directivos, Gerencia, Rectoría y usuarios con permiso
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|administrador|directivo|gerencia|rectoria|ver-reportes-entrevistas'])->group(function () {
    Route::livewire('/reportes', 'pages::reportes.index')->name('reportes.index');
    Route::get('/reportes/imprimir/entrevistas-profesor', [ReportesPrintController::class, 'entrevistasProfesor'])->name('reportes.imprimir.profesores');
    Route::get('/reportes/imprimir/problematicas-curso', [ReportesPrintController::class, 'problematicasCurso'])->name('reportes.imprimir.problematicas');
});

// Recepción / Portería — requiere permiso ingresar-apoderado, ver-recepcion o superadmin
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|ingresar-apoderado|ver-recepcion'])->group(function () {
    Route::livewire('/entrevistas/recepcion', 'pages::entrevistas.recepcion')->name('entrevistas.recepcion');
});

// Historial General — requiere permiso de ver entrevistas, superadmin, o rol estudiante
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|estudiante|ver-entrevistas-propias|ver-entrevistas-general'])->group(function () {
    Route::livewire('/entrevistas', 'pages::entrevistas.index')->name('entrevistas.index');
});

// Agenda y Agendar Entrevista — requiere permiso ver-entrevistas-propias o crear-entrevistas, o superadmin
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|ver-entrevistas-propias|crear-entrevistas'])->group(function () {
    Route::livewire('/entrevistas/agenda', 'pages::entrevistas.agenda')->name('entrevistas.agenda');
    Route::livewire('/entrevistas/crear', 'pages::entrevistas.crear')->name('entrevistas.crear');
});

// Bitácora — protegida por Policy (cualquier autenticado puede intentar, la policy decide)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/entrevistas/{entrevista}/bitacora', 'pages::entrevistas.bitacora')
        ->name('entrevistas.bitacora')
        ->middleware('can:view,entrevista');

    Route::livewire('/sin-permiso', 'pages::auth.sin-permiso')->name('sin-permiso');
});

// Ruta pública para Firma Digital por correo
Route::livewire('/entrevistas/firma/{token}', 'pages::entrevistas.firma_publica')->name('entrevistas.firma_publica');

// Ruta pública para Confirmar o Rechazar Asistencia a Entrevista por correo
Route::livewire('/entrevistas/confirmacion/{token}', 'pages::entrevistas.confirmacion_publica')->name('entrevistas.confirmacion_publica');

// Vistas de Estudiantes — requiere permiso ver-estudiantes o superadmin
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|ver-estudiantes'])->group(function () {
    Route::livewire('/estudiantes', 'pages::usuarios.estudiantes.index')->name('estudiantes.index');
    Route::livewire('/estudiantes/ficha/{id}', 'pages::usuarios.estudiantes.ficha')->name('estudiantes.ficha');
});

// Control de Atrasos — Requiere permiso ingresar-atrasos o superadmin
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|ingresar-atrasos'])->group(function () {
    Route::livewire('/atrasos', 'pages::atrasos.index')->name('atrasos.index');
});

// Historial y Tickets de Atrasos — Requiere permiso ver-atrasos, ingresar-atrasos o superadmin
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|ver-atrasos|ingresar-atrasos'])->group(function () {
    Route::livewire('/atrasos/historial', 'pages::atrasos.historial')->name('atrasos.historial');
    Route::get('/atrasos/ticket/{atraso}', [AtrasoPrintController::class, 'ticket'])->name('atrasos.ticket');
});

// Gestión de Funcionarios — requiere permiso gestionar-funcionarios o superadmin
Route::middleware(['auth', 'verified', 'role_or_permission:superadmin|gestionar-funcionarios'])->group(function () {
    Route::livewire('/funcionarios', 'pages::usuarios.funcionarios.index')->name('funcionarios.index');
    Route::livewire('/funcionarios/ficha/{id}', 'pages::usuarios.funcionarios.ficha')->name('funcionarios.ficha');
});

// Configuración y Cargas Administrativas — Solo Superadmin
Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
    Route::livewire('/funcionarios/calculadora-horas', 'pages::usuarios.funcionarios.calculadora_horas')->name('funcionarios.calculadora_horas');
    Route::livewire('/funcionarios/carga-masiva', 'pages::usuarios.funcionarios.carga_masiva')->name('funcionarios.carga_masiva');
    Route::livewire('/estudiantes/carga-masiva', 'pages::usuarios.estudiantes.carga_masiva')->name('estudiantes.carga_masiva');
    Route::livewire('/estudiantes/match', 'pages::usuarios.estudiantes.match')->name('estudiantes.match');
    Route::livewire('/estudiantes/sincronizar-correos', 'pages::usuarios.estudiantes.sincronizar_correos')->name('estudiantes.sincronizar_correos');
    Route::livewire('/estudiantes/agregar-rut', 'pages::usuarios.estudiantes.agregar-rut')->name('estudiantes.agregar_rut');
    Route::livewire('/admin/historial-correos', 'pages::admin.mail_logs')->name('admin.mail_logs');
    Route::livewire('/admin/modulos', 'pages::admin.modules')->name('admin.modules');
    Route::livewire('/admin/roles-permisos', 'pages::admin.roles_permissions')->name('admin.roles_permissions');
});

Route::get('/office', function () {
    return redirect()->away('https://drive.google.com/file/d/1i8T9g1mlSsUj4xhGGC6-Y99Fwe30fMy7/view?usp=sharing');
})->name('office');

// Módulo de Adquisiciones e Inventario — Fase 1 (Crear)
Route::middleware(['auth', 'verified', 'role:solicitante_adquisiciones,administrador,superadmin'])->group(function () {
    Route::livewire('/adquisiciones/crear', 'pages::adquisiciones.crear')->name('adquisiciones.crear');
});

// Aprobación y Recepción — Solo Administrador y Superadmin (NO directivos)
Route::middleware(['auth', 'verified', 'role:administrador,superadmin'])->group(function () {
    Route::livewire('/adquisiciones/revision', 'pages::adquisiciones.revision')->name('adquisiciones.revision');
    Route::livewire('/adquisiciones/compras', 'pages::adquisiciones.compras')->name('adquisiciones.compras');
});

// Inventario General — Solo Administrador, Superadmin y TI (NO directivos)
Route::middleware(['auth', 'verified', 'role_or_permission:ti|administrador|superadmin|ver-prestamos-general'])->group(function () {
    Route::livewire('/inventario', 'pages::inventario.index')->name('inventario.index');
    Route::livewire('/inventario/detalles/{id}', 'pages::inventario.detalles')->name('inventario.detalles');
});

// Módulo de Informática (TI) — Préstamos y Tareas
Route::middleware(['auth', 'verified', 'role_or_permission:ti|administrador|superadmin|gestionar-prestamos'])->group(function () {
    Route::livewire('/ti/prestamos', 'pages::ti.prestamos.index')->name('ti.prestamos.index');
    Route::livewire('/ti/prestamos/crear', 'pages::ti.prestamos.crear')->name('ti.prestamos.crear');
    Route::livewire('/ti/tareas', 'pages::ti.tareas.index')->name('ti.tareas.index');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/ti/mis-prestamos', 'pages::ti.prestamos.mis_prestamos')->name('ti.prestamos.mis_prestamos');
});

Route::view('/plantilla', 'pages.plantilla1')->name('plantilla');

require __DIR__.'/settings.php';
