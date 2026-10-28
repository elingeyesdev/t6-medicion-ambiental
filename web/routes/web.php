<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\InicioController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'paginas.inicio')->name('inicio');
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'acceso.entrar')->name('login');
    Route::post('/login', [AccesoController::class, 'entrar'])->middleware('throttle:15,1')->name('acceso.entrar');
    Route::view('/register', 'acceso.registro')->name('register');
    Route::post('/register', [AccesoController::class, 'registrar'])->middleware('throttle:5,1')->name('acceso.registrar');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/mapa', [InicioController::class, 'index'])->name('mapa');
    Route::get('/modulos/{modulo}', [InicioController::class, 'modulo'])->name('modulos.mostrar');
    Route::post('/logout', [AccesoController::class, 'salir'])->name('logout');
});
