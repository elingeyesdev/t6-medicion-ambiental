<?php

namespace App\Providers;

use App\Services\Sequias\LectorJsonLocal;
use App\Services\Sequias\ProcedenciaSeriePrecipitacion;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\ValidadorSeriePrecipitacion;
use App\Services\Territorios\CatalogoTerritorial;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
