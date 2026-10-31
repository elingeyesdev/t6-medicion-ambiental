<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\AlertasController;
use App\Http\Controllers\Api\BuscarLugarController;
use App\Http\Controllers\Api\TiempoController;
use App\Http\Controllers\AvisosController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MapaMonitoreoController;
use App\Http\Controllers\Mapas\CapasController;
use App\Http\Controllers\Mapas\PoiController;
use App\Http\Controllers\RecuperacionController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\SensoresController;
use App\Http\Controllers\SimulacionContinuaController;
use App\Http\Controllers\SimulacionesController;
use App\Http\Controllers\UnidadesController;
use App\Http\Controllers\UsuariosController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'paginas.inicio')->name('inicio');
Route::middleware(['auth', 'cuenta.activa', 'can:mapa.ver', 'can:datos.consultar'])->group(function (): void {
    Route::get('/mapa/capas', [CapasController::class, 'index'])
        ->middleware('throttle:120,1,mapa-capas')->name('mapa.capas');
    Route::get('/mapa/capas/cuadros/{cuadro}.png', [CapasController::class, 'imagen'])
        ->where('cuadro', '[a-z0-9][a-z0-9_-]{0,79}')
        ->middleware('throttle:120,1,mapa-imagenes')->name('mapa.capas.imagen');
    Route::get('/mapa/poi', PoiController::class)
        ->middleware('throttle:12,1,mapa-poi')->name('mapa.poi');
});
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
    Route::get('/simulacion-continua/estado', [SimulacionContinuaController::class, 'estado'])
        ->middleware(['can:datos.consultar', 'throttle:120,1']);
    Route::post('/simulacion-continua/accion', [SimulacionContinuaController::class, 'accion'])
        ->middleware(['can:simulaciones.gestionar', 'throttle:30,1']);
    Route::get('/simulaciones/{id}/estado', [SimulacionesController::class, 'mostrar'])
        ->middleware(['can:simulaciones.gestionar', 'throttle:120,1']);
    Route::middleware('can:datos.consultar')->group(function (): void {
        Route::put('/avisos/push', [AvisosController::class, 'push'])->middleware('throttle:10,1')->name('avisos.push');
        Route::get('/avisos/servicio-push.js', [AvisosController::class, 'servicioPush'])->name('avisos.servicio-push');
        Route::get('/avisos/novedades', [AvisosController::class, 'novedades'])->middleware('throttle:30,1')->name('avisos.novedades');
        Route::put('/avisos/canales', [AvisosController::class, 'canales'])->middleware('throttle:10,1')->name('avisos.canales');
    });
    Route::put('/cuenta/clave', [CuentaController::class, 'clave'])->middleware('throttle:5,1')->name('cuenta.clave');
    Route::put('/cuenta/unidades', [UnidadesController::class, 'guardar'])->name('cuenta.unidades');
    Route::middleware('can:datos.consultar')->group(function (): void {
        Route::get('/mapa/buscar', BuscarLugarController::class)->middleware('throttle:mapa-busquedas')->name('mapa.buscar');
        Route::get('/mapa/tiempo', TiempoController::class)->middleware('throttle:tiempo-consultas')->name('mapa.tiempo');
        Route::get('/alertas', [AlertasController::class, 'index'])->name('alertas');
        Route::get('/umbrales', [AlertasController::class, 'umbrales'])->name('umbrales');
        Route::get('/avisos', [AvisosController::class, 'index'])->name('avisos');
        Route::put('/avisos/preferencias', [AvisosController::class, 'preferencias'])->name('avisos.preferencias');
        Route::patch('/avisos/{id}/leer', [AvisosController::class, 'leer'])->name('avisos.leer');
    });
    Route::patch('/alertas/{id}/reconocer', [AlertasController::class, 'reconocer'])->middleware('can:alertas.gestionar')->name('alertas.reconocer');
    Route::middleware('can:reportes.ver')->group(function (): void {
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes');
        Route::get('/reportes/{id}', [ReportesController::class, 'mostrar'])->name('reportes.mostrar');
        Route::get('/reportes/{id}/descargar/{formato}', [ReportesController::class, 'descargar'])->where('formato', 'pdf|xlsx')->name('reportes.descargar');
        Route::get('/plantillas', [ReportesController::class, 'plantillas'])->name('plantillas');
    });
    Route::middleware('can:reportes.crear')->group(function (): void {
        Route::post('/reportes', [ReportesController::class, 'guardar'])->name('reportes.guardar');
        Route::post('/plantillas', [ReportesController::class, 'guardarPlantilla'])->name('plantillas.guardar');
        Route::patch('/plantillas/{id}', [ReportesController::class, 'guardarPlantilla'])->name('plantillas.actualizar');
        Route::delete('/plantillas/{id}', [ReportesController::class, 'archivarPlantilla'])->name('plantillas.archivar');
    });
    Route::get('/simulaciones', [SimulacionesController::class, 'index'])->middleware('can:datos.consultar')->name('simulaciones');
    Route::middleware('can:simulaciones.gestionar')->group(function (): void {
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
