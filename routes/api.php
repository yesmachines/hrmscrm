<?php

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\IdeaController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\VisitController;
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
        Route::get('leaves/holidays', [LeaveController::class, 'festivals'])->name('api.v1.leaves.holidays');
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

        // Visits Endpoints
        Route::get('visits', [VisitController::class, 'index'])->name('api.v1.visits.index');
        Route::post('visits', [VisitController::class, 'store'])->name('api.v1.visits.store');
        Route::get('visits/{visit}', [VisitController::class, 'show'])->name('api.v1.visits.show');
        Route::post('visits/{visit}/approve', [VisitController::class, 'approve'])->name('api.v1.visits.approve');
        Route::post('visits/{visit}/reject', [VisitController::class, 'reject'])->name('api.v1.visits.reject');
        Route::post('visits/{visit}/status', [VisitController::class, 'updateStatus'])->name('api.v1.visits.status');

        // Assets Endpoints (Employee & Admin/HR)
        Route::get('assets', [AssetController::class, 'index'])->name('api.v1.assets.index');
        Route::get('assets/my-assigned', [AssetController::class, 'assignedDropdown'])->name('api.v1.assets.my-assigned');
        Route::get('assets/categories', [AssetController::class, 'categories'])->name('api.v1.assets.categories');
        Route::get('assets/requests', [AssetController::class, 'requests'])->name('api.v1.assets.requests');
        Route::post('assets/requests', [AssetController::class, 'submitRequest'])->name('api.v1.assets.submit-request');
        Route::get('assets/requests/{asset_request}', [AssetController::class, 'requestDetails'])->name('api.v1.assets.requests.show');
        Route::post('assets/requests/{asset_request}/approve', [AssetController::class, 'approveRequest'])->name('api.v1.assets.requests.approve');
        Route::post('assets/requests/{asset_request}/reject', [AssetController::class, 'rejectRequest'])->name('api.v1.assets.requests.reject');
        Route::post('assets/requests/{asset_request}/status', [AssetController::class, 'updateRequestStatus'])->name('api.v1.assets.requests.status');
        Route::get('assets/{asset}', [AssetController::class, 'show'])->name('api.v1.assets.show');
        Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])->name('api.v1.assets.assign');
        Route::post('assets/{asset}/return', [AssetController::class, 'returnAsset'])->name('api.v1.assets.return');
    });
});
