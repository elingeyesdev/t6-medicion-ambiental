<?php

// Copiar este bloque a web/routes/web.php. No necesita modificar api.php.
use App\Http\Controllers\Mapas\CapasController;
use App\Http\Controllers\Mapas\PoiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'cuenta.activa', 'can:mapa.ver', 'can:datos.consultar'])->group(function (): void {
    Route::get('/mapa/capas', [CapasController::class, 'index'])
        ->middleware('throttle:120,1,mapa-capas')->name('mapa.capas');
    Route::get('/mapa/capas/cuadros/{cuadro}.png', [CapasController::class, 'imagen'])
        ->where('cuadro', '[a-z0-9][a-z0-9_-]{0,79}')
        ->middleware('throttle:120,1,mapa-imagenes')->name('mapa.capas.imagen');
    Route::get('/mapa/poi', PoiController::class)
        ->middleware('throttle:12,1,mapa-poi')->name('mapa.poi');
});
