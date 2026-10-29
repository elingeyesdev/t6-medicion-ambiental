<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MapaMonitoreoController;
use App\Http\Controllers\RecuperacionController;
use App\Http\Controllers\SensoresController;
use App\Http\Controllers\SimulacionesController;
use App\Http\Controllers\UsuariosController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'paginas.inicio')->name('inicio');
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'acceso.entrar')->name('login');
    Route::post('/login', [AccesoController::class, 'entrar'])->middleware('throttle:cuentas-ingreso')->name('acceso.entrar');
    Route::view('/register', 'acceso.registro')->name('register');
    Route::post('/register', [AccesoController::class, 'registrar'])->middleware('throttle:cuentas-registro')->name('acceso.registrar');
    Route::view('/recuperar-acceso', 'acceso.recuperar')->name('password.request');
    Route::post('/recuperar-acceso', [RecuperacionController::class, 'solicitar'])->middleware('throttle:cuentas-recuperacion')->name('password.email');
    Route::get('/restablecer-acceso/{token}', [RecuperacionController::class, 'formulario'])->name('password.reset');
    Route::post('/restablecer-acceso', [RecuperacionController::class, 'restablecer'])->middleware('throttle:cuentas-restablecer')->name('password.update');
});
Route::middleware(['auth', 'cuenta.activa'])->group(function (): void {
    Route::middleware('can:simulaciones.gestionar')->group(function (): void {
        Route::get('/simulaciones', [SimulacionesController::class, 'index'])->name('simulaciones');
        Route::post('/simulaciones', [SimulacionesController::class, 'guardar'])->name('simulaciones.guardar');
        Route::post('/simulaciones/{id}/accion', [SimulacionesController::class, 'accion'])->name('simulaciones.accion');
        Route::post('/simulaciones/{id}/historial', [SimulacionesController::class, 'publicar'])->name('simulaciones.publicar');
    });
    Route::get('/sensores', [SensoresController::class, 'index'])->middleware('can:datos.consultar')->name('sensores');
    Route::middleware('can:datos.gestionar')->group(function (): void {
        Route::post('/sensores', [SensoresController::class, 'guardar'])->name('sensores.guardar');
        Route::post('/sensores/importar', [SensoresController::class, 'importar'])->name('sensores.importar');
        Route::post('/sensores/{punto:codigo}/lecturas', [SensoresController::class, 'registrar'])->name('sensores.registrar');
        Route::patch('/sensores/{punto:codigo}/estado', [SensoresController::class, 'estado'])->name('sensores.estado');
    });
    Route::get('/mapa', MapaMonitoreoController::class)->middleware('can:mapa.ver')->name('mapa');
    Route::get('/mapa/puntos', App\Http\Controllers\Api\MapaMonitoreoController::class)->middleware('can:datos.consultar')->name('mapa.puntos');
    Route::get('/modulos/{modulo}', [InicioController::class, 'modulo'])->name('modulos.mostrar');
    Route::post('/logout', [AccesoController::class, 'salir'])->name('logout');
    Route::get('/cuenta', [CuentaController::class, 'index'])->name('cuenta');
    Route::put('/cuenta/nombre', [CuentaController::class, 'nombre'])->name('cuenta.nombre');
    Route::put('/cuenta/preferencias', [CuentaController::class, 'preferencias'])->name('cuenta.preferencias');
    Route::middleware('can:usuarios.administrar')->group(function (): void {
        Route::get('/usuarios', [UsuariosController::class, 'index'])->name('usuarios');
        Route::patch('/usuarios/{usuario}/rol', [UsuariosController::class, 'rol'])->name('usuarios.rol');
        Route::patch('/usuarios/{usuario}/estado', [UsuariosController::class, 'estado'])->name('usuarios.estado');
    });
});
