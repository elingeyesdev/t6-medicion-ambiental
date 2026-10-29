<?php

namespace App\Providers;

use App\Enums\RolCuenta;
use App\Models\User;
use App\Services\Sequias\AlmacenPublicacionesSequias;
use App\Services\Sequias\Experimental\CatalogoMuestrasSequias;
use App\Services\Sequias\LectorJsonLocal;
use App\Services\Sequias\ProcedenciaSeriePrecipitacion;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\ValidadorSeriePrecipitacion;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\GeometriaTerritorial;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CatalogoTerritorial::class,
            fn () => new CatalogoTerritorial(config('focos.datos_territoriales')));
        $this->app->singleton(RepositorioSeriesPrecipitacion::class,
            fn ($app) => new RepositorioSeriesPrecipitacion(config('sequias.directorio_series'),
                $app->make(LectorJsonLocal::class), $app->make(ValidadorSeriePrecipitacion::class),
                $app->make(ProcedenciaSeriePrecipitacion::class)));
        $this->app->singleton(AlmacenPublicacionesSequias::class,
            fn ($app) => new AlmacenPublicacionesSequias(config('sequias.directorio_publicaciones'), $app->make(LectorJsonLocal::class)));
        $this->app->singleton(CatalogoMuestrasSequias::class,
            fn ($app) => new CatalogoMuestrasSequias(config('sequias.archivo_muestras'), dirname(config('focos.datos_territoriales')),
                $app->make(LectorJsonLocal::class), $app->make(CatalogoTerritorial::class), $app->make(GeometriaTerritorial::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mapa-busquedas', fn (Request $request) => Limit::perMinute(12)->by((string) $request->user()?->id));
        RateLimiter::for('tiempo-consultas', fn (Request $request) => Limit::perMinute(12)->by((string) $request->user()?->id));
        foreach (['cuentas-ingreso' => 15, 'cuentas-registro' => 5, 'cuentas-recuperacion' => 5, 'cuentas-restablecer' => 5] as $nombre => $maximo) {
            RateLimiter::for($nombre, fn (Request $request) => Limit::perMinute($maximo)->by($request->ip()));
        }
        foreach (RolCuenta::Administrador->permisos() as $permiso) {
            Gate::define($permiso, fn (User $usuario): bool => $usuario->activo && in_array($permiso, $usuario->rol->permisos(), true));
        }
    }
}
