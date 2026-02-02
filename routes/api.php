<?php

use App\Http\Controllers\Telepecas\TelepecasProxyController;
use Illuminate\Support\Facades\Route;

Route::prefix('telepecas')->group(function () {
    Route::post('{endpoint}', [TelepecasProxyController::class, 'call'])
        ->where('endpoint', '.*');
});

