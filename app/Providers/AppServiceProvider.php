<?php

namespace App\Providers;

use App\Services\Catalog\CatalogProvider;
use App\Services\Telepecas\TelepecasCatalogService;
use App\Services\TpSoftware\TpSoftwareCatalogService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CatalogProvider::class, function () {
            $provider = (string) config('storefront.catalog_provider', 'telepecas');

            return match ($provider) {
                'tpsoftware' => $this->app->make(TpSoftwareCatalogService::class),
                default => $this->app->make(TelepecasCatalogService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
