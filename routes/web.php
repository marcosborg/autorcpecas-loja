<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/loja');
});

Route::prefix('loja')->group(function () {
    Route::get('/', [\App\Http\Controllers\Store\StoreCategoryController::class, 'index']);
    Route::get('categorias', [\App\Http\Controllers\Store\StoreCategoryController::class, 'index']);
    Route::get('categorias/{slug}', [\App\Http\Controllers\Store\StoreCategoryController::class, 'show']);
    Route::get('produtos/{idOrReference}', [\App\Http\Controllers\Store\StoreProductController::class, 'show']);
    Route::get('pesquisa', [\App\Http\Controllers\Store\StoreSearchController::class, 'index']);
});
