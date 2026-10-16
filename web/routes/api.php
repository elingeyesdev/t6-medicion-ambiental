<?php

use App\Http\Controllers\Api\FocosCalorController;
use App\Http\Controllers\Api\TerritoriosController;
use App\Http\Controllers\SaludController;
use App\Services\Api\ErrorConsulta;
use Illuminate\Support\Facades\Route;

// Comprobación operativa, independiente del contrato ambiental de #18.
Route::get('/salud', SaludController::class)->name('api.salud');

Route::prefix('v1')->middleware('throttle:30,1')->group(function (): void {
    Route::get('/territorios', [TerritoriosController::class, 'index']);
    Route::get('/territorios/{identificador}/limite', [TerritoriosController::class, 'limite']);
    Route::get('/focos-calor', FocosCalorController::class);
    foreach (['sequias', 'inundaciones', 'deforestacion', 'calidad-aire'] as $modulo) {
        Route::get('/'.$modulo, function (): never {
            throw new ErrorConsulta('MODULO_NO_DISPONIBLE', 'Este módulo aún no tiene integración operativa.', 503);
        });
    }
});
