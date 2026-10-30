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

    expect($doc->fresh()->status)->toBe('approved')
        ->and($doc->fresh()->remarks)->toBe('Passport verified against physical original copy.')
        ->and($doc->histories()->where('action_type', 'approved')->latest('id')->value('remarks'))
        ->toBe('Passport verified against physical original copy.');
});

test('admin document edit page is available only to the matching creator role when enabled before approval', function () {
    $admin = createHrmsLoginUser('admin');
    $hr = createHrmsLoginUser('hr');

    $salesUser = SalesCrmUser::query()->create([
        'name' => 'Editable CRM Employee',
        'email' => 'editable.crm.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => 'EMP-EDIT-001',
        'designation' => 'Analyst',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $documentType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $documentType->update([
        'editable_before_approval' => true,
        'field_requires' => [],
    ]);
    $adminCreatedDocument = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $documentType->id,
        'document_title' => 'Admin Created Passport',
        'created_by' => $admin->id,
        'status' => 'submitted',
    ]);
    $hrCreatedDocument = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $documentType->id,
        'document_title' => 'HR Created Passport',
        'created_by' => $hr->id,
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('employee-documents.edit', $adminCreatedDocument))
        ->assertOk();

    $this->actingAs($admin)
        ->put(route('employee-documents.update', $adminCreatedDocument), [
            'employee_id' => $employee->id,
            'category_id' => $documentType->category_id,
            'document_type_id' => $documentType->id,
            'document_title' => 'Updated Admin Passport',
            'document_number' => 'EDIT-123',
            'remarks' => 'Checked by the document creator role.',
        ])
        ->assertRedirect(route('employee-documents.show', $adminCreatedDocument));

    expect($adminCreatedDocument->fresh()->document_title)->toBe('Updated Admin Passport')
        ->and($adminCreatedDocument->histories()->latest('id')->value('remarks'))
        ->toBe('Document details updated by Admin');

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('employee-documents.edit', $hrCreatedDocument))
        ->assertForbidden();

    $documentType->update(['editable_before_approval' => false]);
    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('employee-documents.edit', $adminCreatedDocument))
        ->assertForbidden();
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
            'category_id' => $offerType->category_id,
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

test('admin visa upload validates and saves uid based on field_requires', function () {
    $admin = createHrmsLoginUser('admin');

    $salesUser = SalesCrmUser::query()->create([
        'name' => 'Visa Upload Employee',
        'email' => 'visa.upload@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => 'EMP-CRM-VISA',
        'designation' => 'Specialist',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $visaType = DocumentType::query()->where('document_code', 'visa')->firstOrFail();
    $visaType->update([
        'field_requires' => [
            'ref_number' => true,
            'issue_date' => true,
            'expiry_date' => true,
            'attachments' => true,
            'uid' => true,
            'total_experience' => true,
        ],
    ]);

    $this->actingAs($admin)
        ->from(route('employee-documents.create'))
        ->post(route('employee-documents.store'), [
            'category_id' => $visaType->category_id,
            'document_type_id' => $visaType->id,
            'document_title' => 'Employee Visa',
        ])
        ->assertRedirect(route('employee-documents.create'))
        ->assertSessionHasErrors([
            'employee_id',
            'document_number',
            'issue_date',
            'expiry_date',
            'file',
            'uid',
            'total_experience',
        ]);

    $this->actingAs($admin)
        ->from(route('employee-documents.create'))
        ->post(route('employee-documents.store'), [
            'employee_id' => $employee->id,
            'document_type_id' => $visaType->id,
        ])
        ->assertRedirect(route('employee-documents.create'))
        ->assertSessionHasErrors([
            'category_id',
            'document_title',
            'document_number',
            'issue_date',
            'expiry_date',
            'file',
            'uid',
            'total_experience',
        ]);

    $this->actingAs($admin)
        ->from(route('employee-documents.create'))
        ->post(route('employee-documents.store'), [
            'employee_id' => $employee->id,
            'category_id' => $visaType->category_id,
            'document_type_id' => $visaType->id,
            'document_title' => 'Employee Visa',
        ])
        ->assertRedirect(route('employee-documents.create'))
        ->assertSessionHasErrors([
            'document_number',
            'issue_date',
            'expiry_date',
            'file',
            'uid',
            'total_experience',
        ]);

    $this->actingAs($admin)
        ->from(route('employee-documents.create'))
        ->post(route('employee-documents.store'), [
            'employee_id' => $employee->id,
            'category_id' => $visaType->category_id,
            'document_type_id' => $visaType->id,
            'document_title' => 'Employee Visa',
            'document_number' => 'VISA-REF-100',
            'issue_date' => now()->addDay()->toDateString(),
            'expiry_date' => now()->toDateString(),
            'uid' => '123456789',
            'total_experience' => '4.5',
            'file' => UploadedFile::fake()->create('visa.pdf', 300, 'application/pdf'),
        ])
        ->assertRedirect(route('employee-documents.create'))
        ->assertSessionHasErrors(['issue_date', 'expiry_date']);

    $this->actingAs($admin)
        ->from(route('employee-documents.create'))
        ->post(route('employee-documents.store'), [
            'employee_id' => $employee->id,
            'category_id' => $visaType->category_id,
            'document_type_id' => $visaType->id,
            'document_title' => 'Employee Visa',
            'document_number' => 'VISA&REF-100',
            'issue_date' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addDay()->toDateString(),
            'uid' => 'VISA-UID-12345',
            'total_experience' => '4.5',
            'file' => UploadedFile::fake()->create('visa.pdf', 300, 'application/pdf'),
        ])
        ->assertRedirect(route('employee-documents.create'))
        ->assertSessionHasErrors(['document_number', 'uid']);

    $this->actingAs($admin)
        ->post(route('employee-documents.store'), [
            'employee_id' => $employee->id,
            'category_id' => $visaType->category_id,
            'document_type_id' => $visaType->id,
            'document_title' => 'Employee Visa',
            'document_number' => 'VISA-REF-100',
            'issue_date' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addDay()->toDateString(),
            'uid' => '123456789012345',
            'total_experience' => '4.5',
            'file' => UploadedFile::fake()->create('visa.pdf', 300, 'application/pdf'),
        ])
        ->assertRedirect(route('employee-documents.index'));

    $document = EmployeeDocument::query()
        ->where('employee_id', $employee->id)
        ->where('document_type_id', $visaType->id)
        ->firstOrFail();

    $this->assertDatabaseHas('employee_request_details', [
        'employee_document_id' => $document->id,
        'field_key' => 'uid',
        'field_value' => '123456789012345',
    ]);
    $this->assertDatabaseHas('employee_request_details', [
        'employee_document_id' => $document->id,
        'field_key' => 'total_experience',
        'field_value' => '4.5',
    ]);
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
