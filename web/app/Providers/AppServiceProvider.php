<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
