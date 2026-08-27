<?php

use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Database\Seeders\DocumentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(DocumentSeeder::class);
});

test('admin can view employee documents in crm and approve submissions', function () {
    $admin = createHrmsLoginUser('admin');

    $salesUser = SalesCrmUser::query()->create([
        'name' => 'CRM Employee',
        'email' => 'crm.emp@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => 'EMP-CRM-001',
        'designation' => 'Analyst',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $passportType = DocumentType::query()->where('document_code', 'passport')->first();

    $doc = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $passportType->id,
        'document_title' => 'Passport',
        'document_number' => 'N98765432',
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('employee-documents.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('employee-documents.show', $doc))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('employee-documents.approve', $doc), [
            'remarks' => 'Passport verified against physical original copy.',
        ])
        ->assertRedirect();

    expect($doc->fresh()->status)->toBe('approved');
});

test('admin can directly upload document for an employee', function () {
    $admin = createHrmsLoginUser('admin');

    $salesUser = SalesCrmUser::query()->create([
        'name' => 'Direct Upload Emp',
        'email' => 'direct.upload@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => 'EMP-CRM-002',
        'designation' => 'Manager',
        'division' => 'HR',
        'status' => 1,
        'has_report' => false,
    ]);

    $offerType = DocumentType::query()->where('document_code', 'offer_letter')->first();
    $file = UploadedFile::fake()->create('offer_letter.pdf', 300, 'application/pdf');

    $this->actingAs($admin)
        ->post(route('employee-documents.store'), [
            'employee_id' => $employee->id,
            'document_type_id' => $offerType->id,
            'document_title' => 'Official Offer Letter 2026',
            'file' => $file,
        ])
        ->assertRedirect(route('employee-documents.index'));

    $createdDoc = EmployeeDocument::query()->where('employee_id', $employee->id)->first();
    expect($createdDoc)->not->toBeNull()
        ->and($createdDoc->status)->toBe('approved');
});
