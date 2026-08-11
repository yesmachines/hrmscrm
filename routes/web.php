<?php

use App\Http\Controllers\Documents\DocumentCategoryController;
use App\Http\Controllers\Documents\DocumentTemplateController;
use App\Http\Controllers\Documents\DocumentTypeController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\Leave\LeaveBalanceController;
use App\Http\Controllers\Leave\LeaveHistoryController;
use App\Http\Controllers\Leave\LeavePolicyController;
use App\Http\Controllers\Leave\LeaveRequestController;
use App\Http\Controllers\Leave\LeaveTypeController;
use App\Http\Controllers\Organisation\OfficeLocationController;
use App\Http\Controllers\Organisation\OrganisationController;
use App\Http\Middleware\EnsureHrmsLoginRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', EnsureHrmsLoginRole::class])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::resource('employees', EmployeeController::class);
    Route::resource('organisations', OrganisationController::class);
    Route::resource('office-locations', OfficeLocationController::class);
    Route::resource('document-categories', DocumentCategoryController::class);
    Route::resource('document-types', DocumentTypeController::class);
    Route::resource('document-templates', DocumentTemplateController::class);
    Route::resource('leave-types', LeaveTypeController::class);
    Route::resource('leave-policies', LeavePolicyController::class);
    Route::resource('leave-requests', LeaveRequestController::class)->only(['index', 'show', 'update']);
    Route::resource('leave-balances', LeaveBalanceController::class);
    Route::resource('leave-histories', LeaveHistoryController::class);

    Route::get('ideas', [IdeaController::class, 'index'])->name('ideas.index');
    Route::get('ideas/{idea}', [IdeaController::class, 'show'])->name('ideas.show');
    Route::post('ideas/{idea}/status', [IdeaController::class, 'updateStatus'])->name('ideas.update-status');
});

require __DIR__.'/settings.php';
