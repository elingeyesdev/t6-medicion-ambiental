<?php

use App\Http\Controllers\AlertasController;
use App\Http\Controllers\Api\CuentaController;
use App\Http\Controllers\Api\FocosCalorController;
use App\Http\Controllers\Api\MapaMonitoreoController;
use App\Http\Controllers\Api\PaisesController;
use App\Http\Controllers\Api\SensoresController;
use App\Http\Controllers\Api\SequiasController;
use App\Http\Controllers\Api\TerritoriosController;
use App\Http\Controllers\Api\TiempoController;
use App\Http\Controllers\Api\UsuariosController;
use App\Http\Controllers\AvisosController;
use App\Http\Controllers\RecuperacionController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\SaludController;
use App\Http\Controllers\SimulacionesController;
use App\Services\Api\ErrorConsulta;
use Illuminate\Support\Facades\Route;

// Comprobación operativa, independiente del contrato ambiental de #18.
Route::get('/salud', SaludController::class)->name('api.salud');

Route::prefix('v1')->middleware('throttle:30,1')->group(function (): void {
    Route::post('/cuenta/registrar', [CuentaController::class, 'registrar'])->middleware('throttle:cuentas-registro');
    Route::post('/cuenta/recuperar', [RecuperacionController::class, 'solicitar'])->middleware('throttle:cuentas-recuperacion');
    Route::post('/cuenta/restablecer', [RecuperacionController::class, 'restablecer'])->middleware('throttle:cuentas-restablecer');
    Route::post('/cuenta/ingresar', [CuentaController::class, 'entrar'])->middleware('throttle:cuentas-ingreso');
    Route::middleware(['auth:sanctum', 'cuenta.activa', 'abilities:cuenta'])->group(function (): void {
        Route::middleware('can:datos.consultar')->group(function (): void {
            Route::get('/tiempo', TiempoController::class)->middleware('throttle:tiempo-consultas');
            Route::get('/alertas', [AlertasController::class, 'index']);
            Route::get('/umbrales', [AlertasController::class, 'umbrales']);
            Route::get('/avisos', [AvisosController::class, 'index']);
            Route::put('/avisos/preferencias', [AvisosController::class, 'preferencias']);
            Route::patch('/avisos/{id}/leer', [AvisosController::class, 'leer']);
        });
        Route::patch('/alertas/{id}/reconocer', [AlertasController::class, 'reconocer'])->middleware('can:alertas.gestionar');
        Route::middleware('can:reportes.ver')->group(function (): void {
            Route::get('/reportes', [ReportesController::class, 'index']);
            Route::get('/reportes/{id}', [ReportesController::class, 'mostrar']);
            Route::get('/reportes/{id}/descargar/{formato}', [ReportesController::class, 'descargar'])->where('formato', 'pdf|xlsx');
            Route::get('/plantillas', [ReportesController::class, 'plantillas']);
        });
        Route::middleware('can:reportes.crear')->group(function (): void {
            Route::post('/reportes', [ReportesController::class, 'guardar']);
            Route::post('/plantillas', [ReportesController::class, 'guardarPlantilla']);
            Route::patch('/plantillas/{id}', [ReportesController::class, 'guardarPlantilla']);
            Route::delete('/plantillas/{id}', [ReportesController::class, 'archivarPlantilla']);
        });
        Route::middleware('can:simulaciones.gestionar')->group(function (): void {
            Route::get('/simulaciones/catalogo', [SimulacionesController::class, 'catalogo']);
            Route::get('/simulaciones', [SimulacionesController::class, 'index']);
            Route::post('/simulaciones', [SimulacionesController::class, 'guardar']);
            Route::get('/simulaciones/{id}', [SimulacionesController::class, 'mostrar']);
            Route::post('/simulaciones/{id}/accion', [SimulacionesController::class, 'accion']);
            Route::post('/simulaciones/{id}/historial', [SimulacionesController::class, 'publicar']);
        });
        Route::middleware('can:datos.consultar')->group(function (): void {
            Route::get('/mapa/puntos', MapaMonitoreoController::class);
            Route::get('/sensores/catalogo', [SensoresController::class, 'catalogo']);
            Route::get('/sensores/cercanos', [SensoresController::class, 'cercanos']);
            Route::get('/sensores', [SensoresController::class, 'index']);
            Route::get('/sensores/{punto:codigo}/lecturas', [SensoresController::class, 'historial']);
        });
        Route::middleware('can:datos.gestionar')->group(function (): void {
            Route::post('/sensores', [SensoresController::class, 'guardar']);
            Route::post('/sensores/lecturas', [SensoresController::class, 'importar']);
            Route::patch('/sensores/{punto:codigo}/estado', [SensoresController::class, 'estado']);
        });
        Route::get('/cuenta', [CuentaController::class, 'mostrar']);
        Route::put('/cuenta/nombre', [CuentaController::class, 'nombre']);
        Route::put('/cuenta/preferencias', [CuentaController::class, 'preferencias']);
        Route::post('/cuenta/salir', [CuentaController::class, 'salir']);
        Route::middleware('can:usuarios.administrar')->group(function (): void {
            Route::get('/usuarios', [UsuariosController::class, 'index']);
            Route::patch('/usuarios/{usuario}/rol', [UsuariosController::class, 'rol']);
            Route::patch('/usuarios/{usuario}/estado', [UsuariosController::class, 'estado']);
        });
    });
    Route::get('/paises', PaisesController::class);
    Route::get('/territorios', [TerritoriosController::class, 'index']);
    Route::get('/territorios/{identificador}/limite', [TerritoriosController::class, 'limite']);
    Route::get('/focos-calor', FocosCalorController::class);
    Route::get('/sequias', [SequiasController::class, 'index']);
    Route::get('/sequias/resumen', [SequiasController::class, 'resumen']);
    Route::get('/sequias/{muestra}', [SequiasController::class, 'detalle'])->where('muestra', '[a-zA-Z0-9_-]{1,80}');
    foreach (['inundaciones', 'deforestacion', 'calidad-aire'] as $modulo) {
        Route::get('/'.$modulo, function (): never {
            throw new ErrorConsulta('MODULO_NO_DISPONIBLE', 'Este módulo aún no tiene integración operativa.', 503);
        });
    }
});
