<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.v1.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::post('ideas', [\App\Http\Controllers\Api\V1\IdeaController::class, 'store'])->name('api.v1.ideas.store');
    });
});
