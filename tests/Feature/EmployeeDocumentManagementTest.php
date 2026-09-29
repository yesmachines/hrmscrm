<?php

use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Database\Seeders\DocumentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employee_managers')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
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

    $this->actingAs($admin)
        ->get(route('employee-documents.file', $createdDoc))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('employee-documents.download', $createdDoc))
        ->assertOk()
        ->assertDownload();
});

test('user can download blank form pdf for education allowance claim, nominee declaration, and air ticket claim', function () {
    $admin = createHrmsLoginUser('admin');

    $codes = ['education_allowance_claim', 'nomination_form', 'air_ticket_claim'];

    foreach ($codes as $code) {
        $type = DocumentType::query()->where('document_code', $code)->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('document-types.blank-form', ['document_type' => $type->id, 'download' => 1]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
});

test('employee can download blank form via api, upload completed form, and hr can approve it', function () {
    $salesUser = SalesCrmUser::query()->create([
        'name' => 'Fahad Employee',
        'email' => 'fahad@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => 'EMP-DOCS-099',
        'designation' => 'Sales Engineer',
        'division' => 'Machinery',
        'status' => 1,
        'has_report' => false,
    ]);

    $token = $salesUser->createToken('test')->plainTextToken;

    // 1. Download/view blank form via API
    $eduType = DocumentType::query()->where('document_code', 'education_allowance_claim')->firstOrFail();

    $apiFormResponse = $this->withToken($token)->getJson("/api/v1/documents/types/{$eduType->id}/blank-form");
    $apiFormResponse->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.document_code', 'education_allowance_claim');

    // 2. Employee uploads filled & signed PDF
    $filledPdf = UploadedFile::fake()->create('filled_education_claim.pdf', 800, 'application/pdf');

    $uploadResponse = $this->withToken($token)->postJson('/api/v1/documents', [
        'document_type_id' => $eduType->id,
        'document_title' => 'Education Allowance Claim 2026',
        'document_number' => 'EDU-CLM-001',
        'remarks' => 'Completed form with school fee receipt attached.',
        'file' => $filledPdf,
    ]);

    $uploadResponse->assertStatus(201);

    $submittedDoc = EmployeeDocument::query()->where('employee_id', $employee->id)->where('document_type_id', $eduType->id)->first();
    expect($submittedDoc)->not->toBeNull()
        ->and($submittedDoc->status)->toBe('submitted');

    // 3. HR approves the document
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->post(route('employee-documents.approve', $submittedDoc), [
            'remarks' => 'School receipts verified, approved for AED 12,000.',
        ])
        ->assertRedirect();

    expect($submittedDoc->fresh()->status)->toBe('approved');
});
