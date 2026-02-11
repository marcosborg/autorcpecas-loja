<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/sobre-nos', [PageController::class, 'about']);
Route::get('/marcas', [PageController::class, 'brands']);
Route::get('/contactos', [PageController::class, 'contacts']);

Route::prefix('loja')->group(function () {
    Route::get('/', [\App\Http\Controllers\Store\StoreCategoryController::class, 'index']);
    Route::get('categorias', [\App\Http\Controllers\Store\StoreCategoryController::class, 'index']);
    Route::get('categorias/{slug}', [\App\Http\Controllers\Store\StoreCategoryController::class, 'show']);
    Route::get('produtos/{idOrReference}', [\App\Http\Controllers\Store\StoreProductController::class, 'show']);
    Route::get('pesquisa', [\App\Http\Controllers\Store\StoreSearchController::class, 'index']);
    Route::get('pesquisa/sugestoes', [\App\Http\Controllers\Store\StoreSearchController::class, 'suggestions']);
    Route::get('checkout/simulador', [\App\Http\Controllers\Store\StoreCheckoutSimulatorController::class, 'index']);

    Route::middleware('guest')->group(function () {
        Route::get('conta/login', [\App\Http\Controllers\Store\StoreAuthController::class, 'showLogin']);
        Route::post('conta/login', [\App\Http\Controllers\Store\StoreAuthController::class, 'login']);
        Route::get('conta/registo', [\App\Http\Controllers\Store\StoreAuthController::class, 'showRegister']);
        Route::post('conta/registo', [\App\Http\Controllers\Store\StoreAuthController::class, 'register']);
    });

    Route::middleware('auth')->group(function () {
        Route::post('conta/logout', [\App\Http\Controllers\Store\StoreAuthController::class, 'logout']);

        Route::get('conta', [\App\Http\Controllers\Store\StoreAccountController::class, 'index']);
        Route::get('conta/moradas', [\App\Http\Controllers\Store\StoreAddressController::class, 'index']);
        Route::get('conta/moradas/create', [\App\Http\Controllers\Store\StoreAddressController::class, 'create']);
        Route::post('conta/moradas', [\App\Http\Controllers\Store\StoreAddressController::class, 'store']);
        Route::get('conta/moradas/{address}/edit', [\App\Http\Controllers\Store\StoreAddressController::class, 'edit']);
        Route::put('conta/moradas/{address}', [\App\Http\Controllers\Store\StoreAddressController::class, 'update']);
        Route::delete('conta/moradas/{address}', [\App\Http\Controllers\Store\StoreAddressController::class, 'destroy']);

        Route::get('carrinho', [\App\Http\Controllers\Store\StoreCartController::class, 'index']);
        Route::post('carrinho/items', [\App\Http\Controllers\Store\StoreCartController::class, 'add']);
        Route::put('carrinho/items/{item}', [\App\Http\Controllers\Store\StoreCartController::class, 'update']);
        Route::delete('carrinho/items/{item}', [\App\Http\Controllers\Store\StoreCartController::class, 'destroy']);

        Route::get('checkout', [\App\Http\Controllers\Store\StoreCheckoutController::class, 'index']);
        Route::post('checkout', [\App\Http\Controllers\Store\StoreCheckoutController::class, 'place']);

        Route::get('conta/encomendas', [\App\Http\Controllers\Store\StoreOrderController::class, 'index']);
        Route::get('conta/encomendas/{order}', [\App\Http\Controllers\Store\StoreOrderController::class, 'show']);
    });
});
