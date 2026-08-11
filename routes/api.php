<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\IdeaController;
use App\Http\Controllers\Api\V1\LeaveController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.v1.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('ideas', [IdeaController::class, 'index'])->name('api.v1.ideas.index');
        Route::post('ideas', [IdeaController::class, 'store'])->name('api.v1.ideas.store');

        Route::get('leaves', [LeaveController::class, 'index'])->name('api.v1.leaves.index');
        Route::get('leaves/meta', [LeaveController::class, 'meta'])->name('api.v1.leaves.meta');
        Route::post('leaves', [LeaveController::class, 'store'])->name('api.v1.leaves.store');
    });
});
