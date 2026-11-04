<?php

// Copiar estas rutas fuera de otros grupos en web/routes/web.php.
// No incluir este archivo automáticamente en producción: el integrador une las rutas.
use App\Http\Controllers\AvisosController;
use App\Http\Controllers\CuentaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'cuenta.activa', 'can:datos.consultar'])->group(function (): void {
    Route::put('/avisos/push', [AvisosController::class, 'push'])
        ->middleware('throttle:10,1')->name('avisos.push');
    Route::get('/avisos/servicio-push.js', [AvisosController::class, 'servicioPush'])->name('avisos.servicio-push');
    Route::get('/avisos/novedades', [AvisosController::class, 'novedades'])
        ->middleware('throttle:30,1')->name('avisos.novedades');
    Route::put('/avisos/canales', [AvisosController::class, 'canales'])
        ->middleware('throttle:10,1')->name('avisos.canales');
});
Route::put('/cuenta/clave', [CuentaController::class, 'clave'])
    ->middleware(['auth', 'cuenta.activa', 'throttle:5,1'])->name('cuenta.clave');
