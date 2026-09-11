<?php

use App\Http\Controllers\Assets\AssetCategoryController;
use App\Http\Controllers\Assets\AssetController;
use App\Http\Controllers\Assets\AssetRequestController;
use App\Http\Controllers\Designation\DesignationController;
use App\Http\Controllers\Documents\DocumentCategoryController;
use App\Http\Controllers\Documents\DocumentReminderController;
use App\Http\Controllers\Documents\DocumentTemplateController;
use App\Http\Controllers\Documents\DocumentTypeController;
use App\Http\Controllers\Documents\EmployeeDocumentController;
use App\Http\Controllers\Documents\HrPolicyController;
use App\Http\Controllers\Documents\LetterRequestController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\Leave\FestivalController;
use App\Http\Controllers\Leave\LeaveBalanceController;
use App\Http\Controllers\Leave\LeaveHistoryController;
use App\Http\Controllers\Leave\LeavePolicyController;
use App\Http\Controllers\Leave\LeaveRequestController;
use App\Http\Controllers\Leave\LeaveTypeController;
use App\Http\Controllers\Organisation\OfficeLocationController;
use App\Http\Controllers\Organisation\OrganisationController;
use App\Http\Controllers\Visits\VisitController as VisitWebController;
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
    Route::resource('designations', DesignationController::class);
    Route::resource('organisations', OrganisationController::class);
    Route::resource('office-locations', OfficeLocationController::class);

    // Documents CRM & Config
    Route::resource('document-categories', DocumentCategoryController::class);
    Route::resource('document-types', DocumentTypeController::class);
    Route::resource('document-templates', DocumentTemplateController::class);

    Route::resource('employee-documents', EmployeeDocumentController::class);
    Route::post('employee-documents/{employee_document}/approve', [EmployeeDocumentController::class, 'approve'])->name('employee-documents.approve');
    Route::post('employee-documents/{employee_document}/reject', [EmployeeDocumentController::class, 'reject'])->name('employee-documents.reject');

    Route::get('letter-requests', [LetterRequestController::class, 'index'])->name('letter-requests.index');
    Route::get('letter-requests/{letter_request}', [LetterRequestController::class, 'show'])->name('letter-requests.show');
    Route::post('letter-requests/{letter_request}/approve', [LetterRequestController::class, 'approve'])->name('letter-requests.approve');
    Route::post('letter-requests/{letter_request}/reject', [LetterRequestController::class, 'reject'])->name('letter-requests.reject');

    Route::get('hr-policies', [HrPolicyController::class, 'index'])->name('hr-policies.index');
    Route::post('hr-policies', [HrPolicyController::class, 'store'])->name('hr-policies.store');

    Route::get('document-reminders', [DocumentReminderController::class, 'index'])->name('document-reminders.index');

    Route::resource('leave-types', LeaveTypeController::class);
    Route::resource('leave-policies', LeavePolicyController::class);
    Route::resource('festivals', FestivalController::class);
    Route::resource('leave-requests', LeaveRequestController::class)->only(['index', 'show', 'update', 'create', 'store']);
    Route::resource('leave-balances', LeaveBalanceController::class);
    Route::resource('leave-histories', LeaveHistoryController::class);

    Route::get('ideas', [IdeaController::class, 'index'])->name('ideas.index');
    Route::get('ideas/{idea}', [IdeaController::class, 'show'])->name('ideas.show');
    Route::post('ideas/{idea}/status', [IdeaController::class, 'updateStatus'])->name('ideas.update-status');

    Route::resource('visits', VisitWebController::class)->only(['index', 'show']);
    Route::post('visits/{visit}/approve', [VisitWebController::class, 'approve'])->name('visits.approve');
    Route::post('visits/{visit}/reject', [VisitWebController::class, 'reject'])->name('visits.reject');
    Route::post('visits/{visit}/status', [VisitWebController::class, 'updateStatus'])->name('visits.update-status');

    // Assets Management
    Route::resource('asset-categories', AssetCategoryController::class);
    Route::resource('assets', AssetController::class);
    Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])->name('assets.assign');
    Route::post('assets-assigned/{assignment}/return', [AssetController::class, 'returnAsset'])->name('assets.return');

    Route::resource('asset-requests', AssetRequestController::class)->only(['index', 'show', 'create', 'store']);
    Route::post('asset-requests/{asset_request}/approve', [AssetRequestController::class, 'approve'])->name('asset-requests.approve');
    Route::post('asset-requests/{asset_request}/reject', [AssetRequestController::class, 'reject'])->name('asset-requests.reject');
    Route::post('asset-requests/{asset_request}/status', [AssetRequestController::class, 'updateStatus'])->name('asset-requests.update-status');
});

require __DIR__.'/settings.php';
