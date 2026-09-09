<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EmployeeController;
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

        // Employees Endpoints
        Route::get('employees', [EmployeeController::class, 'index'])->name('api.v1.employees.index');
        Route::get('employees/{id}', [EmployeeController::class, 'show'])->name('api.v1.employees.show');

        Route::get('ideas', [IdeaController::class, 'index'])->name('api.v1.ideas.index');
        Route::post('ideas', [IdeaController::class, 'store'])->name('api.v1.ideas.store');

        Route::get('leaves', [LeaveController::class, 'index'])->name('api.v1.leaves.index');
        Route::get('leaves/meta', [LeaveController::class, 'meta'])->name('api.v1.leaves.meta');
        Route::get('leaves/festivals', [LeaveController::class, 'festivals'])->name('api.v1.leaves.festivals');
        Route::post('leaves', [LeaveController::class, 'store'])->name('api.v1.leaves.store');
        Route::get('leaves/{id}', [LeaveController::class, 'show'])->name('api.v1.leaves.show');

        // Documents Endpoints (Figma Mobile App)
        Route::get('documents/categories', [DocumentController::class, 'categories'])->name('api.v1.documents.categories');
        Route::get('documents/types', [DocumentController::class, 'types'])->name('api.v1.documents.types');
        Route::get('documents', [DocumentController::class, 'index'])->name('api.v1.documents.index');
        Route::post('documents', [DocumentController::class, 'store'])->name('api.v1.documents.store');
        Route::get('documents/policies', [DocumentController::class, 'policies'])->name('api.v1.documents.policies');
        Route::get('documents/letters', [DocumentController::class, 'letters'])->name('api.v1.documents.letters');
        Route::post('documents/letters', [DocumentController::class, 'requestLetter'])->name('api.v1.documents.request-letter');
        Route::get('documents/letters/{id}', [DocumentController::class, 'letterDetails'])->name('api.v1.documents.letter-details');
        Route::get('documents/{id}', [DocumentController::class, 'show'])->name('api.v1.documents.show');
        Route::put('documents/{id}', [DocumentController::class, 'update'])->name('api.v1.documents.update');
    });
});
