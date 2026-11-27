<?php

use App\Http\Controllers\AccesoBrigadaController;
use App\Http\Controllers\AccesoController;
use App\Http\Controllers\AlertasController;
use App\Http\Controllers\Api\BuscarLugarController;
use App\Http\Controllers\Api\CalidadAireController;
use App\Http\Controllers\Api\InundacionesController;
use App\Http\Controllers\Api\TiempoController;
use App\Http\Controllers\AvisosController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MapaMonitoreoController;
use App\Http\Controllers\Mapas\CapasController;
use App\Http\Controllers\Mapas\PoiController;
use App\Http\Controllers\PortalIncendiosController;
use App\Http\Controllers\RecuperacionController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\RevisionForestalController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\SensoresController;
use App\Http\Controllers\SimulacionContinuaController;
use App\Http\Controllers\SimulacionesController;
use App\Http\Controllers\SolicitudBrigadaController;
use App\Http\Controllers\UnidadesController;
use App\Http\Controllers\UsuariosController;
use App\Http\Middleware\RevisionForestalLocal;
use App\Services\Api\ErrorConsulta;
use App\Services\Inundaciones\CatalogoProductosAgua;
use Illuminate\Support\Facades\Route;

Route::view('/', 'paginas.inicio')->name('inicio');
// Revisión privada: sin entrada pública ni enlace en el menú.
Route::prefix('revision/forestal')->middleware([
    RevisionForestalLocal::class, 'auth', 'cuenta.activa', 'can:mapa.ver', 'can:datos.consultar',
])->group(function (): void {
    Route::get('/', [RevisionForestalController::class, 'index'])
        ->middleware('throttle:30,1,forestal-revision-vista')->name('revision.forestal');
    Route::get('/consulta', [RevisionForestalController::class, 'consultar'])
        ->middleware('throttle:12,1,forestal-revision-consulta')->name('revision.forestal.consulta');
});
Route::middleware(['auth', 'cuenta.activa', 'can:mapa.ver', 'can:datos.consultar'])->group(function (): void {
    Route::get('/mapa/forestal', fn () => view('mapas.forestal', [
        'modulos' => array_filter(config('geocruz.modulos'), fn ($modulo) => $modulo['habilitado']),
        'capa' => config('forestal.capa_publicada'),
    ]))->name('mapa.forestal');
    Route::get('/mapa/agua/catalogo', [InundacionesController::class, 'catalogo'])->middleware('throttle:30,1')->name('mapa.agua.catalogo');
    Route::get('/mapa/agua/consulta', [InundacionesController::class, 'consultar'])->middleware('throttle:30,1')->name('mapa.agua.consulta');
    Route::get('/mapa/agua', function (CatalogoProductosAgua $productos) {
        try {
            abort_if($productos->catalogo()['productos'] === [], 404);
        } catch (ErrorConsulta $error) {
            abort(503, $error->getMessage());
        }

        return app()->call([app(MapaMonitoreoController::class), '__invoke']);
    })->name('mapa.agua');
    Route::get('/revision/agua', function () {
        abort_unless(app()->environment(['local', 'testing']) && config('inundaciones.revision_local'), 404);

        return app()->call([app(MapaMonitoreoController::class), '__invoke']);
    })->name('revision.agua');
    Route::get('/mapa/capas', [CapasController::class, 'index'])
        ->middleware('throttle:120,1,mapa-capas')->name('mapa.capas');
    Route::get('/mapa/capas/cuadros/{cuadro}.png', [CapasController::class, 'imagen'])
        ->where('cuadro', '[a-z0-9][a-z0-9_-]{0,79}')
        ->middleware('throttle:120,1,mapa-imagenes')->name('mapa.capas.imagen');
    Route::get('/mapa/poi', PoiController::class)
        ->middleware('throttle:12,1,mapa-poi')->name('mapa.poi');
});
Route::middleware('guest')->group(function (): void {
    Route::get('/login', fn () => response()->view('acceso.entrar')->header('Cache-Control', 'no-store, private'))->name('login');
    Route::post('/login', [AccesoController::class, 'entrar'])->middleware('throttle:cuentas-ingreso')->name('acceso.entrar');
    Route::view('/register', 'acceso.registro')->name('register');
    Route::post('/register', [AccesoController::class, 'registrar'])->middleware('throttle:cuentas-registro')->name('acceso.registrar');
    Route::view('/recuperar-acceso', 'acceso.recuperar')->name('password.request');
    Route::post('/recuperar-acceso', [RecuperacionController::class, 'solicitar'])->middleware('throttle:cuentas-recuperacion')->name('password.email');
    Route::get('/restablecer-acceso/{token}', [RecuperacionController::class, 'formulario'])->name('password.reset');
    Route::post('/restablecer-acceso', [RecuperacionController::class, 'restablecer'])->middleware('throttle:cuentas-restablecer')->name('password.update');
});
Route::prefix('seguimiento')->name('seguimiento.')->middleware(['auth', 'cuenta.activa', 'can:mapa.ver', 'can:datos.consultar'])->group(function (): void {
    Route::get('/', [SeguimientoController::class, 'index'])->name('index');
    Route::post('/', [SeguimientoController::class, 'guardar'])->name('guardar');
    Route::get('/{id}', [SeguimientoController::class, 'index'])->whereNumber('id')->name('zona');
    Route::post('/{id}/evaluar', [SeguimientoController::class, 'evaluar'])->whereNumber('id')
        ->middleware('throttle:12,1,evaluar-zona')->name('evaluar');
    Route::post('/{id}/tareas', [SeguimientoController::class, 'crearTarea'])->whereNumber('id')->name('tareas');
    Route::patch('/{id}/tareas/{tarea}', [SeguimientoController::class, 'registrarResultado'])
        ->whereNumber(['id', 'tarea'])->name('resultado');
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
        Route::get('/mapa/aire', CalidadAireController::class)->middleware('throttle:aire-consultas')->name('mapa.aire');
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
    Route::get('/simulaciones', [SimulacionesController::class, 'index'])->middleware('can:simulaciones.gestionar')->name('simulaciones');
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

Route::prefix('portal-incendios')->name('portal-incendios.')->group(function (): void {
    Route::get('/', [PortalIncendiosController::class, 'index'])->name('index');
    Route::get('/detecciones/{observacion}', [PortalIncendiosController::class, 'buscar'])
        ->where('observacion', 'firms-[a-f0-9]{64}')->name('buscar');
    Route::get('/brigada/solicitud', [SolicitudBrigadaController::class, 'formulario'])->name('solicitud');
    Route::post('/brigada/solicitud', [SolicitudBrigadaController::class, 'registrar'])
        ->middleware('throttle:5,1,portal-solicitud')->name('solicitud.registrar');
    Route::get('/brigada', [AccesoBrigadaController::class, 'formulario'])->name('brigada');
    Route::post('/brigada', [AccesoBrigadaController::class, 'entrar'])
        ->middleware('throttle:8,1,portal-brigada')->name('brigada.entrar');
    Route::post('/brigada/salir', [AccesoBrigadaController::class, 'salir'])->name('brigada.salir');
    Route::get('/avisos/{aviso}', [PortalIncendiosController::class, 'index'])
        ->whereUuid('aviso')->name('ficha');
    Route::middleware('throttle:20,1,portal-atencion')->group(function (): void {
        Route::post('/avisos/{aviso}/tomar', [PortalIncendiosController::class, 'tomar'])
            ->whereUuid('aviso')->name('tomar');
        Route::post('/avisos/{aviso}/resolver', [PortalIncendiosController::class, 'resolver'])
            ->whereUuid('aviso')->name('resolver');
    });
});
