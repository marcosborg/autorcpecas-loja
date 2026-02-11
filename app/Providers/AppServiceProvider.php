<?php

namespace App\Providers;

use App\Models\CmsMenuItem;
use App\Services\Catalog\CatalogProvider;
use App\Services\Store\CartService;
use App\Services\Database\DbEnvironment;
use App\Services\Telepecas\TelepecasCatalogService;
use App\Services\TpSoftware\TpSoftwareCatalogService;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\QueryException;
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

            try {
                $menuItems = CmsMenuItem::query()
                    ->with('page')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(function (CmsMenuItem $item): array {
                        $href = $item->resolvedUrl();
                        $isExternal = preg_match('/^https?:\\/\\//i', $href) === 1;
                        $isCurrent = false;

                        if (! $isExternal && $href !== '#') {
                            $path = parse_url($href, PHP_URL_PATH);
                            if (is_string($path) && $path !== '') {
                                $clean = trim($path, '/');
                                $isCurrent = request()->is($clean === '' ? '/' : $clean) || request()->is($clean.'/*');
                            }
                        }

                        return [
                            'label' => $item->label,
                            'href' => $href,
                            'open_in_new_tab' => (bool) $item->open_in_new_tab,
                            'is_button' => (bool) $item->is_button,
                            'is_current' => $isCurrent,
                        ];
                    })
                    ->values()
                    ->all();
            } catch (QueryException) {
                $menuItems = [];
            }

            if ($menuItems === []) {
                $menuItems = [
                    ['label' => 'Inicio', 'href' => url('/'), 'open_in_new_tab' => false, 'is_button' => false, 'is_current' => request()->is('/')],
                    ['label' => 'Loja', 'href' => url('/loja'), 'open_in_new_tab' => false, 'is_button' => false, 'is_current' => request()->is('loja*')],
                    ['label' => 'Todas as marcas', 'href' => url('/marcas'), 'open_in_new_tab' => false, 'is_button' => false, 'is_current' => request()->is('marcas')],
                ];
            }

            $view->with('storeCartCount', $count);
            $view->with('headerMenuItems', $menuItems);
        });
    }
}
