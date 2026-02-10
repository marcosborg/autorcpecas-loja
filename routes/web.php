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
});
