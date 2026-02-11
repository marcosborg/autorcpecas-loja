<?php

namespace App\Providers;

use App\Services\Catalog\CatalogProvider;
use App\Services\Store\CartService;
use App\Services\Database\DbEnvironment;
use App\Services\Telepecas\TelepecasCatalogService;
use App\Services\TpSoftware\TpSoftwareCatalogService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        app(DbEnvironment::class)->apply();
        Paginator::useBootstrapFive();

        View::composer('store.*', function ($view): void {
            $count = 0;
            $user = Auth::user();
            if ($user) {
                /** @var CartService $cartService */
                $cartService = app(CartService::class);
                $cart = $cartService->openCartFor($user);
                $count = (int) $cart->items()->sum('quantity');
            }

            $view->with('storeCartCount', $count);
        });
    }
}
