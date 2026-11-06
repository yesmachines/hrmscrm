<?php

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\Organisation;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Services\LetterRequestService;
use Database\Seeders\DocumentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();

    $this->seed(DocumentSeeder::class);
});

test('documents categories hub returns categories with live count', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'John Doe',
        'email' => 'john.documents@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-001',
        'designation' => 'Software Engineer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $passportType = DocumentType::query()->where('document_code', 'passport')->first();

    EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $passportType->id,
        'document_title' => 'Passport',
        'document_number' => 'A123456789',
        'issue_date' => '2026-07-13',
        'expiry_date' => '2036-07-13',
        'status' => 'approved',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/documents/categories');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonStructure([
            'statusCode',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'category_name',
                    'short_code',
                    'document_count',
                    'badge_text',
                    'subcategories',
                ],
            ],
        ]);
});

test('employee policy lists are paginated', function () {
    $employeeUser = SalesCrmUser::query()->create([
        'name' => 'Policy List Employee',
        'email' => 'policy.list.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-POLICY-LIST-001',
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    $employeeResponse = $this->withToken($employeeUser->createToken('test')->plainTextToken)
        ->getJson('/api/v1/documents/policies?page=2&per_page=2');

    $employeeResponse->assertOk()
        ->assertJsonCount(1, 'data.policies')
        ->assertJsonPath('data.pagination.total', 3)
        ->assertJsonPath('data.pagination.per_page', 2)
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.last_page', 2);

});

test('HR policy lists are paginated', function () {
    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'Policy List HR',
        'email' => 'policy.list.hr@example.com',
    ]);

    $hrResponse = $this->withToken($hrUser->createToken('test')->plainTextToken)
        ->getJson('/api/v1/hr/documents/policies?page=2&per_page=2');

    $hrResponse->assertOk()
        ->assertJsonCount(1, 'data.policies')
        ->assertJsonPath('data.pagination.total', 3)
        ->assertJsonPath('data.pagination.per_page', 2)
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.last_page', 2);
});

test('employee letter lists support pagination and status and search filters', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Letter List Employee',
        'email' => 'letter.list.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-LETTER-LIST-001',
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    $nocType = DocumentType::query()->where('document_code', 'noc')->firstOrFail();
    $firstSubmittedLetter = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $nocType->id,
        'document_title' => 'NOC First Request',
        'status' => 'submitted',
    ]);
    $secondSubmittedLetter = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $nocType->id,
        'document_title' => 'NOC Second Request',
        'status' => 'submitted',
    ]);
    $approvedLetter = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $nocType->id,
        'document_title' => 'NOC Approved Request',
        'status' => 'approved',
    ]);

    $token = $user->createToken('test')->plainTextToken;
    $paginatedResponse = $this->withToken($token)
        ->getJson('/api/v1/documents/letters?status=submitted&search=NOC&page=2&per_page=1');

    $paginatedResponse->assertOk()
        ->assertJsonCount(1, 'data.letters')
        ->assertJsonPath('data.letters.0.id', $firstSubmittedLetter->id)
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.last_page', 2);

    $filteredResponse = $this->withToken($token)
        ->getJson('/api/v1/documents/letters?status=approved&search=Approved');

    $filteredResponse->assertOk()
        ->assertJsonCount(1, 'data.letters')
        ->assertJsonPath('data.letters.0.id', $approvedLetter->id)
        ->assertJsonPath('data.pagination.total', 1);
});

test('documents list nests document type details separately from document fields', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Document List User',
        'email' => 'document.list@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-LIST-001',
        'designation' => 'Software Engineer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $passportType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $document = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $passportType->id,
        'document_title' => 'Passport Copy',
        'document_number' => 'A98765432',
        'issue_date' => '2024-01-15',
        'expiry_date' => '2034-01-14',
        'status' => 'submitted',
        'remarks' => 'Renewed passport copy',
    ]);

    $token = $user->createToken('test')->plainTextToken;
    $typesResponse = $this->withToken($token)->getJson('/api/v1/documents/types');
    $typesResponse->assertOk();
    $expectedDocumentType = collect($typesResponse->json('data'))
        ->firstWhere('id', $passportType->id);

    $response = $this->withToken($token)->getJson('/api/v1/documents');

    $response->assertOk()
        ->assertJsonStructure([
            'statusCode',
            'message',
            'data' => [
                'documents' => [
                    '*' => [
                        'id',
                        'document_type' => [
                            'id',
                            'category_id',
                            'document_name',
                            'document_code',
                            'category_name',
                            'category_code',
                            'editable_before_approval',
                            'requires_hr_approval',
                            'requires_reminder',
                            'record_source',
                            'field_requires',
                            'has_blank_form',
                            'blank_form_url',
                            'templates',
                        ],
                        'document_title',
                        'document_number',
                        'status',
                    ],
                ],
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ],
        ])
        ->assertJsonPath('data.documents.0.id', $document->id)
        ->assertJsonPath('data.documents.0.document_title', 'Passport Copy')
        ->assertJsonPath('data.documents.0.document_number', 'A98765432')
        ->assertJsonPath('data.documents.0.status', 'submitted')
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.pagination.per_page', 15);

    $documentData = $response->json('data.documents.0');
    expect($documentData['document_type'])->toBe($expectedDocumentType)
        ->and($documentData)->not->toHaveKey('category_code')
        ->and($documentData)->not->toHaveKey('document_type_id')
        ->and($documentData)->not->toHaveKey('employee_id')
        ->and($documentData)->not->toHaveKey('employee_name')
        ->and($documentData)->not->toHaveKey('document_name')
        ->and($documentData)->not->toHaveKey('document_code');
});

test('employee document list includes company-wide hr policies filtered by category code', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Company Policy User',
        'email' => 'company.policy.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-COMPANY-POLICY-001',
        'designation' => 'Software Engineer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $policyType = DocumentType::query()
        ->where('document_code', 'hr_policy')
        ->firstOrFail();
    $policy = EmployeeDocument::query()->create([
        'employee_id' => 0,
        'document_type_id' => $policyType->id,
        'document_title' => 'Company HR Policy',
        'status' => 'approved',
    ]);

    $token = $user->createToken('test')->plainTextToken;
    $response = $this->withToken($token)
        ->getJson('/api/v1/documents?category_code=hr_docs');

    $response->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.documents.0.id', $policy->id)
        ->assertJsonPath('data.documents.0.document_type.category_code', 'hr_docs');
});

test('employee document edit option and endpoint require creator, enabled type, and pre-approval status', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Editable Document Employee',
        'email' => 'editable.document.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-EDIT-001',
        'designation' => 'Specialist',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);
    $documentType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $documentType->update([
        'editable_before_approval' => true,
        'field_requires' => [],
    ]);
    $document = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $documentType->id,
        'document_title' => 'Employee Passport',
        'created_by' => $user->id,
        'status' => 'draft',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonPath('data.documents.0.can_edit', true);

    $this->withToken($token)
        ->putJson("/api/v1/documents/{$document->id}", [
            'document_title' => 'Updated Employee Passport',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $document->update(['status' => 'approved']);
    $this->withToken($token)
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonPath('data.documents.0.can_edit', false);

    $this->withToken($token)
        ->putJson("/api/v1/documents/{$document->id}", [
            'document_title' => 'Approved documents cannot be edited',
        ])
        ->assertForbidden();
});

test('hr document list includes documents belonging to other employees', function () {
    $employeeUser = SalesCrmUser::query()->create([
        'name' => 'Employee Document Owner',
        'email' => 'employee.document.owner@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-DOCS-HR-LIST-001',
        'designation' => 'Software Engineer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $passportType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $document = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $passportType->id,
        'document_title' => 'Employee Passport',
        'document_number' => 'HR-LIST-001',
        'status' => 'submitted',
    ]);

    $otherEmployeeUser = SalesCrmUser::query()->create([
        'name' => 'Other Document Owner',
        'email' => 'other.document.owner@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $otherEmployee = Employee::query()->create([
        'user_id' => $otherEmployeeUser->id,
        'emp_num' => 'EMP-DOCS-HR-LIST-002',
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    $otherDocument = EmployeeDocument::query()->create([
        'employee_id' => $otherEmployee->id,
        'document_type_id' => $passportType->id,
        'document_title' => 'Other Employee Passport',
        'document_number' => 'HR-LIST-002',
        'status' => 'approved',
    ]);

    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'HR Document List User',
        'email' => 'hr.document.list@example.com',
    ]);
    $hrToken = $hrUser->createToken('test')->plainTextToken;
    $hrResponse = $this->withToken($hrToken)->getJson('/api/v1/hr/documents?per_page=1&page=2');

    $hrResponse->assertOk()
        ->assertJsonPath('data.documents.0.id', $otherDocument->id)
        ->assertJsonPath('data.documents.0.employee_id', $otherEmployee->id)
        ->assertJsonPath('data.documents.0.employee_name', 'Other Document Owner')
        ->assertJsonPath('data.documents.0.document_title', 'Other Employee Passport')
        ->assertJsonCount(1, 'data.documents')
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.last_page', 2);

    $this->withToken($hrToken)
        ->getJson("/api/v1/hr/documents/{$otherDocument->id}")
        ->assertOk()
        ->assertJsonPath('data.employee_id', $otherEmployee->id)
        ->assertJsonPath('data.employee_name', 'Other Document Owner');

    $employeeToken = $employeeUser->createToken('test')->plainTextToken;
    $this->withToken($employeeToken)
        ->getJson('/api/v1/hr/documents')
        ->assertForbidden();

    $employeeListResponse = $this->withToken($employeeToken)->getJson('/api/v1/documents');
    $employeeListResponse->assertOk()
        ->assertJsonPath('data.documents.0.id', $document->id)
        ->assertJsonCount(1, 'data.documents')
        ->assertJsonPath('data.pagination.total', 1);
});

test('hr can approve and reject documents with remarks stored on the document and its history', function () {
    $documentType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $approvedDocument = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $documentType->id,
        'document_title' => 'Passport for Approval',
        'status' => 'submitted',
    ]);
    $rejectedDocument = EmployeeDocument::query()->create([
        'employee_id' => 102,
        'document_type_id' => $documentType->id,
        'document_title' => 'Passport for Rejection',
        'status' => 'under_review',
    ]);
    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'Document Status HR User',
        'email' => 'document.status.hr@example.com',
    ]);
    $hrToken = $hrUser->createToken('test')->plainTextToken;

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/{$approvedDocument->id}/status", [
            'status' => 'approved',
            'remarks' => 'Verified against original.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.remarks', 'Verified against original.');

    expect($approvedDocument->fresh()->remarks)->toBe('Verified against original.')
        ->and($approvedDocument->histories()->where('action_type', 'approved')->latest('id')->value('remarks'))
        ->toBe('Verified against original.');

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/{$rejectedDocument->id}/status", [
            'status' => 'rejected',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['remarks']);

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/{$rejectedDocument->id}/status", [
            'status' => 'rejected',
            'remarks' => 'Upload is unreadable.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.remarks', 'Upload is unreadable.');

    expect($rejectedDocument->fresh()->remarks)->toBe('Upload is unreadable.')
        ->and($rejectedDocument->histories()->where('action_type', 'rejected')->latest('id')->value('remarks'))
        ->toBe('Upload is unreadable.');
});

test('hr document edit is restricted by creator role, type setting, and pre-approval status', function () {
    $documentType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $documentType->update([
        'editable_before_approval' => true,
        'field_requires' => ['uid' => true],
    ]);

    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'HR Document Editor',
        'email' => 'hr.document.editor@example.com',
    ]);
    $adminUser = createHrmsLoginUser('admin', [
        'name' => 'Admin Document Editor',
        'email' => 'admin.document.editor@example.com',
    ]);

    $hrDocument = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $documentType->id,
        'document_title' => 'HR Created Document',
        'created_by' => $hrUser->id,
        'status' => 'under_review',
    ]);
    $approvedDocument = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $documentType->id,
        'document_title' => 'Approved Document',
        'created_by' => $hrUser->id,
        'status' => 'approved',
    ]);

    $hrToken = $hrUser->createToken('test')->plainTextToken;
    $adminToken = $adminUser->createToken('test')->plainTextToken;

    $this->withToken($hrToken)
        ->getJson("/api/v1/hr/documents/{$hrDocument->id}")
        ->assertOk()
        ->assertJsonPath('data.can_edit', true);

    $this->withToken($hrToken)
        ->putJson("/api/v1/hr/documents/{$hrDocument->id}", [
            'document_title' => 'Updated by HR',
            'uid' => '001234',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    expect($hrDocument->requestDetails()->where('field_key', 'uid')->value('field_value'))
        ->toBe('001234')
        ->and($hrDocument->histories()->latest('id')->value('remarks'))
        ->toBe('Document details updated by HR');

    $this->withToken($adminToken)
        ->putJson("/api/v1/hr/documents/{$hrDocument->id}", [
            'document_title' => 'Updated by another role',
        ])
        ->assertForbidden();

    $this->withToken($hrToken)
        ->putJson("/api/v1/hr/documents/{$approvedDocument->id}", [
            'document_title' => 'Approved documents cannot be edited',
        ])
        ->assertForbidden();
});

test('hr document edit history records the acting role', function () {
    $documentType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $documentType->update([
        'editable_before_approval' => true,
        'field_requires' => [],
    ]);

    $hrUser = createHrmsLoginUser('hr');
    $document = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $documentType->id,
        'document_title' => 'HR editable document',
        'created_by' => $hrUser->id,
        'status' => 'submitted',
    ]);

    $this->withToken($hrUser->createToken('test')->plainTextToken)
        ->putJson("/api/v1/hr/documents/{$document->id}", [
            'document_title' => 'Updated HR document',
        ])
        ->assertOk();

    expect($document->histories()->latest('id')->value('remarks'))
        ->toBe('Document details updated by HR');
});

test('hr document list filters by employee and document type only on the hr endpoint', function () {
    $employeeUsers = collect([
        ['name' => 'First Filter Employee', 'email' => 'first.filter.employee@example.com'],
        ['name' => 'Second Filter Employee', 'email' => 'second.filter.employee@example.com'],
    ])->map(fn (array $attributes) => SalesCrmUser::query()->create([
        ...$attributes,
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]));

    $employees = $employeeUsers->values()->map(fn (SalesCrmUser $user, int $index) => Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-FILTER-'.($index + 1),
        'designation' => 'Specialist',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]));

    $passportType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $visaType = DocumentType::query()->where('document_code', 'visa')->firstOrFail();
    $firstEmployee = $employees->first();

    $firstPassport = EmployeeDocument::query()->create([
        'employee_id' => $firstEmployee->id,
        'document_type_id' => $passportType->id,
        'document_title' => 'First Employee Passport',
        'status' => 'submitted',
    ]);
    $firstVisa = EmployeeDocument::query()->create([
        'employee_id' => $firstEmployee->id,
        'document_type_id' => $visaType->id,
        'document_title' => 'First Employee Visa',
        'status' => 'submitted',
    ]);
    EmployeeDocument::query()->create([
        'employee_id' => $employees->last()->id,
        'document_type_id' => $visaType->id,
        'document_title' => 'Second Employee Visa',
        'status' => 'submitted',
    ]);

    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'Document Filter HR User',
        'email' => 'document.filter.hr@example.com',
    ]);
    $hrToken = $hrUser->createToken('test')->plainTextToken;

    $this->withToken($hrToken)
        ->getJson('/api/v1/hr/documents?employee_id='.$firstEmployee->id)
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);

    $this->withToken($hrToken)
        ->getJson('/api/v1/hr/documents?document_type_id='.$visaType->id)
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);

    $this->withToken($hrToken)
        ->getJson('/api/v1/hr/documents?employee_id='.$firstEmployee->id.'&document_type_id='.$visaType->id)
        ->assertOk()
        ->assertJsonPath('data.documents.0.id', $firstVisa->id)
        ->assertJsonPath('data.pagination.total', 1);

    $employeeToken = $employeeUsers->first()->createToken('test')->plainTextToken;
    $this->withToken($employeeToken)
        ->getJson('/api/v1/documents?employee_id='.$employees->last()->id.'&document_type_id='.$passportType->id)
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.documents.0.id', $firstVisa->id);
});

test('documents upload endpoint allows employee to upload document with file', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Sanub User',
        'email' => 'sanub@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-002',
        'designation' => 'Specialist',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $eidType = DocumentType::query()->where('document_code', 'emirates_id')->first();
    $token = $user->createToken('test')->plainTextToken;

    $file = UploadedFile::fake()->create('emirates_id.pdf', 500, 'application/pdf');

    $response = $this->withToken($token)->postJson('/api/v1/documents', [
        'document_type_id' => $eidType->id,
        'ref_number' => '784-1990-1234567-1',
        'document_title' => 'Emirates ID',
        'issue_date' => '2026-07-13',
        'expiry_date' => '2026-10-13',
        'remarks' => 'Renewed card',
        'file' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('statusCode', 201);

    $doc = EmployeeDocument::query()->where('employee_id', $employee->id)->where('document_number', '784-1990-1234567-1')->first();
    expect($doc)->not->toBeNull()
        ->and($doc->status)->toBe('submitted');

    // 3-month reminder should have been created
    $reminder = DB::table('document_reminders')->where('employee_document_id', $doc->id)->first();
    expect($reminder)->not->toBeNull();
});

test('hr document API creates documents for the selected employee with HR audit details', function () {
    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'HR Document Uploader',
        'email' => 'hr.document.uploader@example.com',
    ]);

    $targetUser = SalesCrmUser::query()->create([
        'name' => 'HR Document Target Employee',
        'email' => 'hr.document.target@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $targetEmployee = Employee::query()->create([
        'user_id' => $targetUser->id,
        'emp_num' => 'EMP-HR-DOCS-TARGET',
        'designation' => 'Analyst',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);
    $profile = EmployeeProfile::query()->create([
        'employee_id' => $targetEmployee->id,
    ]);

    $passportType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $passportType->update(['requires_hr_approval' => false]);
    $token = $hrUser->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/hr/documents', [
            'document_type_id' => $passportType->id,
            'document_number' => 'HR-PASSPORT-INVALID',
            'file' => UploadedFile::fake()->create('missing-employee.pdf', 500, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id']);

    $response = $this->withToken($token)->postJson('/api/v1/hr/documents', [
        'employee_id' => $targetEmployee->id,
        'document_type_id' => $passportType->id,
        'document_number' => 'HR-PASSPORT-001',
        'document_title' => 'Passport',
        'issue_date' => '2024-01-15',
        'expiry_date' => '2034-01-14',
        'file' => UploadedFile::fake()->create('passport.pdf', 500, 'application/pdf'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.employee_id', $targetEmployee->id)
        ->assertJsonPath('data.employee_name', 'HR Document Target Employee')
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.message', 'Document uploaded successfully.');

    $document = EmployeeDocument::query()
        ->where('employee_id', $targetEmployee->id)
        ->where('document_type_id', $passportType->id)
        ->firstOrFail();
    $this->withToken($token)
        ->getJson("/api/v1/hr/documents/{$document->id}")
        ->assertOk()
        ->assertJsonPath('data.employee_id', $targetEmployee->id)
        ->assertJsonPath('data.employee_name', 'HR Document Target Employee');

    $fileRecord = $document->files()->firstOrFail();
    expect($document->created_by)->toBe($hrUser->id)
        ->and($fileRecord->uploaded_by)->toBe($hrUser->id)
        ->and($fileRecord->change_notes)->toBe('Uploaded by HR')
        ->and($document->histories()->latest('id')->value('remarks'))
        ->toBe('Document uploaded and approved by HR')
        ->and($profile->fresh()->dob_passport?->format('Y-m-d'))
        ->toBe('2024-01-15');

    $passportType->update(['requires_hr_approval' => true]);
    $pendingResponse = $this->withToken($token)->postJson('/api/v1/hr/documents', [
        'employee_id' => $targetEmployee->id,
        'document_type_id' => $passportType->id,
        'document_number' => 'HR-PASSPORT-002',
        'document_title' => 'Passport renewal',
        'issue_date' => '2025-01-15',
        'expiry_date' => '2035-01-14',
        'file' => UploadedFile::fake()->create('passport-renewal.pdf', 500, 'application/pdf'),
    ]);

    $pendingResponse->assertStatus(201);
    $pendingDocument = EmployeeDocument::query()
        ->where('employee_id', $targetEmployee->id)
        ->where('document_title', 'Passport renewal')
        ->firstOrFail();
    expect($pendingDocument->files()->firstOrFail()->change_notes)
        ->toBe('Uploaded by HR; awaiting HR approval');

    $adminUser = createHrmsLoginUser('admin', [
        'name' => 'Admin Document Uploader',
        'email' => 'admin.document.uploader@example.com',
    ]);

    $adminEmployee = Employee::query()->create([
        'user_id' => $adminUser->id,
        'emp_num' => 'EMP-ADMIN-DOCS-001',
        'designation' => 'Administrator',
        'division' => 'Administration',
        'status' => 1,
        'has_report' => false,
    ]);
    $adminToken = $adminUser->createToken('test')->plainTextToken;
    Auth::forgetGuards();

    $adminResponse = $this->withToken($adminToken)->postJson('/api/v1/hr/documents', [
        'employee_id' => $adminEmployee->id,
        'document_type_id' => $passportType->id,
        'document_number' => 'ADMIN-PASSPORT-001',
        'document_title' => 'Passport',
        'issue_date' => '2024-02-15',
        'expiry_date' => '2034-02-14',
        'file' => UploadedFile::fake()->create('admin-passport.pdf', 500, 'application/pdf'),
    ]);

    $adminResponse->assertStatus(201);

    $adminDocument = EmployeeDocument::query()
        ->where('employee_id', $adminEmployee->id)
        ->where('document_type_id', $passportType->id)
        ->firstOrFail();
    expect($adminDocument->files()->firstOrFail()->change_notes)
        ->toBe('Uploaded by Admin; awaiting HR approval');

    $financeUser = createHrmsLoginUser('finance', [
        'name' => 'Finance Document Uploader',
        'email' => 'finance.document.uploader@example.com',
    ]);
    $financeEmployee = Employee::query()->create([
        'user_id' => $financeUser->id,
        'emp_num' => 'EMP-FINANCE-DOCS-001',
        'designation' => 'Finance Officer',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);
    $financeToken = $financeUser->createToken('test')->plainTextToken;
    Auth::forgetGuards();

    $financeResponse = $this->withToken($financeToken)->postJson('/api/v1/hr/documents', [
        'employee_id' => $financeEmployee->id,
        'document_type_id' => $passportType->id,
        'document_number' => 'FINANCE-PASSPORT-001',
        'document_title' => 'Passport',
        'issue_date' => '2024-03-15',
        'expiry_date' => '2034-03-14',
        'file' => UploadedFile::fake()->create('finance-passport.pdf', 500, 'application/pdf'),
    ]);
    $financeResponse->assertStatus(201);

    $financeDocument = EmployeeDocument::query()
        ->where('employee_id', $financeEmployee->id)
        ->where('document_type_id', $passportType->id)
        ->firstOrFail();
    expect($financeDocument->files()->firstOrFail()->change_notes)
        ->toBe('Uploaded by Finance; awaiting HR approval');
});

test('hr document uploads persist configured request details', function () {
    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'HR Experience Uploader',
        'email' => 'hr.experience.uploader@example.com',
    ]);
    $employeeUser = SalesCrmUser::query()->create([
        'name' => 'Experience Document Employee',
        'email' => 'experience.document.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-HR-EXPERIENCE-001',
        'designation' => 'Specialist',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);
    $type = DocumentType::query()->where('document_code', 'experience')->firstOrFail();
    $token = $hrUser->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/hr/documents', [
        'employee_id' => $employee->id,
        'document_type_id' => $type->id,
        'issue_date' => '2024-01-01',
        'total_experience' => 8.5,
        'file' => UploadedFile::fake()->create('experience.pdf', 500, 'application/pdf'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.request_details.total_experience', '8.5');
});

test('document type API exposes configured fields and document uploads save extra values as request details', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Dynamic Fields User',
        'email' => 'dynamic.fields@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-DYNAMIC',
        'designation' => 'Specialist',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $type = DocumentType::query()->where('document_code', 'experience')->firstOrFail();
    $token = $user->createToken('test')->plainTextToken;

    $typesResponse = $this->withToken($token)->getJson('/api/v1/documents/types?category_code=personal');
    $typesResponse->assertOk();
    expect($typesResponse->json())->not->toHaveKey('params');

    $visa = collect($typesResponse->json('data'))->firstWhere('document_code', 'visa');
    expect($visa['field_requires'])->toBe([
        'ref_number' => true,
        'expiry_date' => true,
        'attachments' => true,
        'uid' => true,
    ]);

    $letterTypesResponse = $this->withToken($token)
        ->getJson('/api/v1/documents/types?category_code=letter_requests');
    $letterTypesResponse->assertOk();
    $noc = collect($letterTypesResponse->json('data'))->firstWhere('document_code', 'noc');
    expect($noc['field_requires'])->toBe([
        'to_address' => true,
        'visa_designation' => true,
        'destination_country' => true,
        'purpose' => true,
    ]);

    $file = UploadedFile::fake()->create('experience.pdf', 500, 'application/pdf');
    $response = $this->withToken($token)->postJson('/api/v1/documents', [
        'document_type_id' => $type->id,
        'issue_date' => '2026-10-28',
        'total_experience' => 8.5,
        'custom_note' => 'Additional information not configured on the type',
        'file' => $file,
    ]);

    $response->assertCreated();

    $documentId = $response->json('data.id');
    $this->assertDatabaseHas('employee_request_details', [
        'employee_document_id' => $documentId,
        'field_key' => 'total_experience',
        'field_value' => '8.5',
    ]);
    $this->assertDatabaseHas('employee_request_details', [
        'employee_document_id' => $documentId,
        'field_key' => 'custom_note',
        'field_value' => 'Additional information not configured on the type',
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/documents/{$documentId}")
        ->assertOk()
        ->assertJsonPath('data.request_details.total_experience', '8.5');
});

test('document upload API does not require values based on field_requires', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Required Fields User',
        'email' => 'required.fields@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-REQUIRED',
        'designation' => 'Specialist',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $type = DocumentType::query()->where('document_code', 'experience')->firstOrFail();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/documents', [
        'document_type_id' => $type->id,
        'file' => UploadedFile::fake()->create('experience.pdf', 500, 'application/pdf'),
    ]);

    $response->assertCreated();
});

test('documents letter request endpoint submits noc request', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Swathika User',
        'email' => 'swathika@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-003',
        'designation' => 'Executive',
        'division' => 'Sales',
        'status' => 1,
        'has_report' => false,
    ]);

    $nocType = DocumentType::query()->where('document_code', 'noc')->first();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/documents/letters', [
        'document_type_id' => $nocType->id,
        'purpose' => 'Employment Visa Process',
        'details' => 'Requesting NOC for embassy submission.',
        'to_address' => 'Embassy of Germany',
        'visa_designation' => 'Senior Engineer',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['details', 'destination_country']);

    $this->withToken($token)->postJson('/api/v1/documents/letters', [
        'document_type_id' => $nocType->id,
        'purpose' => 'Employment Visa Process',
        'to_address' => 'Embassy of Germany',
        'visa_designation' => 'Senior Engineer',
        'destination_country' => 'Germany',
        'details' => null,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['details']);

    $response = $this->withToken($token)->postJson('/api/v1/documents/letters', [
        'document_type_id' => $nocType->id,
        'purpose' => 'Employment Visa Process',
        'to_address' => 'Embassy of Germany',
        'visa_designation' => 'Senior Engineer',
        'destination_country' => 'Germany',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('statusCode', 201);

    $letter = EmployeeDocument::query()->where('employee_id', $employee->id)->first();
    expect($letter)->not->toBeNull();

    // Check letter details endpoint
    $detailsResponse = $this->withToken($token)->getJson("/api/v1/documents/letters/{$letter->id}");
    $detailsResponse->assertOk()
        ->assertJsonPath('data.purpose', 'Employment Visa Process')
        ->assertJsonPath('data.details', null)
        ->assertJsonPath('data.destination_country', 'Germany');

    expect($letter->requestDetails()->where('field_key', 'destination_country')->value('field_value'))
        ->toBe('Germany');
});

test('documents upload and letter requests accept organisation_id and document_template_id', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Org Doc User',
        'email' => 'orgdoc@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOCS-004',
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    $org = Organisation::query()->firstOrCreate(
        ['short_name' => 'TEST_ORG'],
        ['org_name' => 'Test Organisation', 'status' => 1]
    );

    $docType = DocumentType::query()->where('document_code', 'passport')->first();
    $template = DocumentTemplate::query()->create([
        'document_type_id' => $docType->id,
        'organisation_id' => $org->id,
        'template_name' => 'Org Passport Template',
        'template_code' => 'org_passport_tpl',
        'status' => 1,
    ]);

    $token = $user->createToken('test')->plainTextToken;
    $file = UploadedFile::fake()->create('passport.pdf', 500, 'application/pdf');

    // 1. Upload document with organisation_id and document_template_id
    $uploadResponse = $this->withToken($token)->postJson('/api/v1/documents', [
        'document_type_id' => $docType->id,
        'document_template_id' => $template->id,
        'organisation_id' => $org->id,
        'document_number' => 'ORG-PASSPORT-01',
        'document_title' => 'Org Passport Copy',
        'issue_date' => '2025-01-01',
        'expiry_date' => '2030-01-01',
        'file' => $file,
    ]);

    $uploadResponse->assertStatus(201);

    $uploadedDoc = EmployeeDocument::query()->where('document_number', 'ORG-PASSPORT-01')->first();
    expect($uploadedDoc)->not->toBeNull()
        ->and($uploadedDoc->organisation_id)->toBe($org->id)
        ->and($uploadedDoc->document_template_id)->toBe($template->id);

    // 2. Query documents filtered by organisation_id
    $listResponse = $this->withToken($token)->getJson("/api/v1/documents?organisation_id={$org->id}");
    $listResponse->assertOk()
        ->assertJsonPath('statusCode', 200);

    $items = collect($listResponse->json('data.documents'));
    expect($items->pluck('id'))->toContain($uploadedDoc->id);

    // 3. Submit letter request with organisation_id
    $nocType = DocumentType::query()->where('document_code', 'noc')->first();
    $letterResponse = $this->withToken($token)->postJson('/api/v1/documents/letters', [
        'document_type_id' => $nocType->id,
        'document_template_id' => $template->id,
        'organisation_id' => $org->id,
        'purpose' => 'Bank Loan Application',
        'to_address' => 'Bank',
        'visa_designation' => 'Analyst',
        'destination_country' => 'Germany',
    ]);

    $letterResponse->assertStatus(201);
    $letterDoc = EmployeeDocument::query()->where('employee_id', $employee->id)->where('document_type_id', $nocType->id)->first();
    expect($letterDoc)->not->toBeNull()
        ->and($letterDoc->organisation_id)->toBe($org->id)
        ->and($letterDoc->document_template_id)->toBe($template->id);
});

test('employee letter submission requires configured fields and stores salary details for HR review', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Salary Transfer Employee',
        'email' => 'salary.transfer.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-SALARY-TRANSFER-001',
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    $documentType = DocumentType::query()
        ->where('document_code', 'salary_transfer_letter')
        ->firstOrFail();
    $template = DocumentTemplate::query()->create([
        'document_type_id' => $documentType->id,
        'template_name' => 'Salary Transfer Test Template',
        'template_code' => '{{currency}} {{gross_salary}} {{iban_number}} {{to_address}} {{purpose}}',
        'status' => 1,
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/documents/letters', [
            'document_type_id' => $documentType->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'salary_amount',
            'currency',
            'bank_iban',
            'to_address',
            'purpose',
        ]);

    $response = $this->withToken($token)
        ->postJson('/api/v1/documents/letters', [
            'document_type_id' => $documentType->id,
            'salary_amount' => 8500.50,
            'currency' => 'US',
            'bank_iban' => 'AE070331234567890123456',
            'to_address' => 'Bank of Example',
            'purpose' => 'Salary transfer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);

    $response = $this->withToken($token)
        ->postJson('/api/v1/documents/letters', [
            'document_type_id' => $documentType->id,
            'document_template_id' => $template->id,
            'salary_amount' => 8500.50,
            'currency' => 'AED',
            'bank_iban' => 'AE070331234567890123456',
            'to_address' => 'Bank of Example',
            'purpose' => 'Salary transfer',
        ])
        ->assertCreated();

    $letterRequestId = $response->json('data.id');
    $letter = EmployeeDocument::query()->findOrFail($letterRequestId);
    expect($letter->employee_id)->toBe($employee->id)
        ->and($letter->requestDetails()->pluck('field_value', 'field_key')->all())->toMatchArray([
            'salary_amount' => '8500.5',
            'currency' => 'AED',
            'bank_iban' => 'AE070331234567890123456',
            'to_address' => 'Bank of Example',
            'purpose' => 'Salary transfer',
        ]);

    $employeeDetailsResponse = $this->withToken($token)
        ->getJson("/api/v1/documents/letters/{$letter->id}");

    expect($employeeDetailsResponse->status())->toBe(200, $employeeDetailsResponse->content());
    $employeeDetailsResponse
        ->assertJsonPath('data.salary_amount', '8500.5')
        ->assertJsonPath('data.currency', 'AED')
        ->assertJsonPath('data.bank_iban', 'AE070331234567890123456');

});

test('hr can list view approve and reject employee letter requests only', function () {
    $employeeUser = SalesCrmUser::query()->create([
        'name' => 'Letter Request Employee',
        'email' => 'letter.request.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-LETTER-001',
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    $secondEmployeeUser = SalesCrmUser::query()->create([
        'name' => 'Second Letter Employee',
        'email' => 'second.letter.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $secondEmployee = Employee::query()->create([
        'user_id' => $secondEmployeeUser->id,
        'emp_num' => 'EMP-LETTER-002',
        'designation' => 'Coordinator',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $nocType = DocumentType::query()->where('document_code', 'noc')->firstOrFail();
    $passportType = DocumentType::query()->where('document_code', 'passport')->firstOrFail();
    $template = DocumentTemplate::query()->create([
        'document_type_id' => $nocType->id,
        'template_name' => 'NOC Letter Template',
        'template_code' => 'To {{to_address}}: {{purpose}} / {{details}} for {{employee_name}} traveling to {{destination_country}}',
        'status' => 1,
    ]);

    $approvedLetter = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $nocType->id,
        'document_template_id' => $template->id,
        'document_title' => 'NOC Request',
        'status' => 'submitted',
    ]);
    $approvedLetter->requestDetails()->createMany([
        ['field_name' => 'Purpose', 'field_key' => 'purpose', 'field_value' => 'Visa application'],
        ['field_name' => 'Address', 'field_key' => 'to_address', 'field_value' => 'Embassy'],
        ['field_name' => 'Destination Country', 'field_key' => 'destination_country', 'field_value' => 'Germany'],
    ]);

    $rejectedLetter = EmployeeDocument::query()->create([
        'employee_id' => $secondEmployee->id,
        'document_type_id' => $nocType->id,
        'document_title' => 'Second NOC Request',
        'status' => 'under_review',
    ]);

    $salaryTransferType = DocumentType::query()
        ->where('document_code', 'salary_transfer_letter')
        ->firstOrFail();
    $salaryTransferLetter = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $salaryTransferType->id,
        'document_title' => 'Salary Transfer Request',
        'status' => 'submitted',
    ]);
    $salaryTransferLetter->requestDetails()->createMany([
        ['field_name' => 'Salary Amount', 'field_key' => 'salary_amount', 'field_value' => '8500.5'],
        ['field_name' => 'Currency', 'field_key' => 'currency', 'field_value' => 'AED'],
        ['field_name' => 'IBAN', 'field_key' => 'bank_iban', 'field_value' => 'AE070331234567890123456'],
        ['field_name' => 'Address', 'field_key' => 'to_address', 'field_value' => 'Bank of Example'],
        ['field_name' => 'Purpose', 'field_key' => 'purpose', 'field_value' => 'Salary transfer'],
    ]);

    EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $passportType->id,
        'document_title' => 'Passport Upload',
        'status' => 'submitted',
    ]);

    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'Letter Request HR',
        'email' => 'letter.request.hr@example.com',
    ]);
    $hrToken = $hrUser->createToken('test')->plainTextToken;

    $letterListResponse = $this->withToken($hrToken)
        ->getJson('/api/v1/hr/documents/letters')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 3);

    $letterListResponse->assertJsonFragment(['employee_name' => 'Letter Request Employee'])
        ->assertJsonFragment(['employee_name' => 'Second Letter Employee']);

    $this->withToken($hrToken)
        ->getJson("/api/v1/hr/documents/letters/{$approvedLetter->id}")
        ->assertOk()
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.employee_name', 'Letter Request Employee')
        ->assertJsonPath('data.purpose', 'Visa application')
        ->assertJsonPath(
            'data.rendered_html',
            'To Embassy: Visa application /  for Letter Request Employee traveling to Germany',
        )
        ->assertJsonPath('data.field_requires.to_address', true)
        ->assertJsonPath('data.field_requires.visa_designation', true)
        ->assertJsonPath('data.field_requires.purpose', true)
        ->assertJsonPath('data.approval_fields', [])
        ->assertJsonPath('data.request_details.purpose', 'Visa application')
        ->assertJsonPath('data.details', null);

    $hrSalaryTransferDetailsResponse = $this->withToken($hrToken)
        ->getJson("/api/v1/hr/documents/letters/{$salaryTransferLetter->id}")
        ->assertOk()
        ->assertJsonPath('data.salary_amount', '8500.5')
        ->assertJsonPath('data.currency', 'AED')
        ->assertJsonPath('data.bank_iban', 'AE070331234567890123456')
        ->assertJsonPath('data.approval_fields', [])
        ->assertJsonPath('data.field_requires.bank_iban', true);
    expect($hrSalaryTransferDetailsResponse->json('data.rendered_html'))
        ->toContain('AED 8,500.50')
        ->toContain('AE070331234567890123456');

    $salaryCertificateRequirements = DocumentType::query()
        ->where('document_code', 'salary_certificate')
        ->firstOrFail()
        ->field_requires;
    ksort($salaryCertificateRequirements);

    $salaryTransferRequirements = DocumentType::query()
        ->where('document_code', 'salary_transfer_letter')
        ->firstOrFail()
        ->field_requires;
    ksort($salaryTransferRequirements);

    expect($salaryCertificateRequirements)->toBe([
        'currency' => true,
        'purpose' => true,
        'salary_amount' => true,
    ])
        ->and($salaryTransferRequirements)->toBe([
            'bank_iban' => true,
            'currency' => true,
            'purpose' => true,
            'salary_amount' => true,
            'to_address' => true,
        ]);

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/letters/{$approvedLetter->id}/approve", [
            'remarks' => 'Verified.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['details']);

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/letters/{$approvedLetter->id}/approve", [
            'to_address' => 'Embassy',
            'visa_designation' => 'Project Manager',
            'purpose' => 'Visa application',
            'details' => 'Employment verification for the German embassy.',
            'remarks' => 'Verified.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to_address', 'visa_designation', 'purpose'])
        ->assertJsonPath(
            'errors.purpose.0',
            'Employee-submitted fields are read-only during HR approval. Only details and remarks may be submitted.',
        );

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/letters/{$approvedLetter->id}/approve", [
            'details' => 'Employment verification for the German embassy.',
            'remarks' => 'Verified.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.details', 'Employment verification for the German embassy.')
        ->assertJsonPath('data.has_generated_file', true);

    $this->withToken($hrToken)
        ->getJson("/api/v1/hr/documents/letters/{$approvedLetter->id}")
        ->assertOk()
        ->assertJsonPath('data.details', 'Employment verification for the German embassy.')
        ->assertJsonPath(
            'data.rendered_html',
            'To Embassy: Visa application / Employment verification for the German embassy. for Letter Request Employee traveling to Germany',
        );

    expect($approvedLetter->fresh()->requestDetails()
        ->where('field_key', 'details')
        ->value('field_name'))->toBe('HR Provided Details');

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/letters/{$salaryTransferLetter->id}/approve", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['details']);

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/letters/{$salaryTransferLetter->id}/approve", [
            'salary_amount' => '8500.50',
            'currency' => 'US',
            'bank_iban' => 'AE070331234567890123456',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['details', 'salary_amount', 'currency', 'bank_iban']);

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/letters/{$salaryTransferLetter->id}/approve", [
            'details' => 'Transfer the employee salary to the registered bank account.',
            'remarks' => 'Salary and IBAN verified.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.details', 'Transfer the employee salary to the registered bank account.');

    expect($approvedLetter->fresh()->remarks)
        ->toBe('Verified.')
        ->and($approvedLetter->fresh()->histories()->where('action_type', 'approved')->value('remarks'))
        ->toBe('Verified.')
        ->and($approvedLetter->fresh()->files()->exists())
        ->toBeTrue();

    $hrSalaryTransferApprovedResponse = $this->withToken($hrToken)
        ->getJson("/api/v1/hr/documents/letters/{$salaryTransferLetter->id}")
        ->assertOk()
        ->assertJsonPath('data.purpose', 'Salary transfer')
        ->assertJsonPath('data.details', 'Transfer the employee salary to the registered bank account.');
    expect($hrSalaryTransferApprovedResponse->json('data.rendered_html'))
        ->toContain('Salary transfer');

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/letters/{$approvedLetter->id}/approve")
        ->assertStatus(409);

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/letters/{$rejectedLetter->id}/reject", [
            'remarks' => 'Please provide the destination details.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.remarks', 'Please provide the destination details.');

    $this->withToken($hrToken)
        ->postJson("/api/v1/hr/documents/{$approvedLetter->id}/status", [
            'status' => 'rejected',
            'remarks' => 'Should not bypass letter processing.',
        ])
        ->assertStatus(409);

    $this->withToken($hrToken)
        ->postJson('/api/v1/hr/documents/letters', [])
        ->assertStatus(405);

});

test('employees cannot access the HR letter request review queue', function () {
    $employeeUser = SalesCrmUser::factory()->create();

    $this->withToken($employeeUser->createToken('test')->plainTextToken)
        ->getJson('/api/v1/hr/documents/letters')
        ->assertForbidden();
});

test('admin letter approval keeps employee fields read-only and saves HR details', function () {
    $nocType = DocumentType::query()->where('document_code', 'noc')->firstOrFail();
    $nocTemplate = DocumentTemplate::query()->create([
        'document_type_id' => $nocType->id,
        'template_name' => 'NOC HR Details Test Template',
        'template_code' => 'To {{to_address}}: {{purpose}} / {{details}} for {{employee_name}} traveling to {{destination_country}}',
        'status' => 1,
    ]);
    $letterRequest = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $nocType->id,
        'document_template_id' => $nocTemplate->id,
        'document_title' => 'NOC Request',
        'status' => 'submitted',
    ]);
    $letterRequest->requestDetails()->createMany([
        ['field_name' => 'Purpose', 'field_key' => 'purpose', 'field_value' => 'Employee purpose'],
        ['field_name' => 'Address', 'field_key' => 'to_address', 'field_value' => 'Embassy of Germany'],
        ['field_name' => 'Visa Designation', 'field_key' => 'visa_designation', 'field_value' => 'Analyst'],
        ['field_name' => 'Destination Country', 'field_key' => 'destination_country', 'field_value' => 'Germany'],
        ['field_name' => 'Details', 'field_key' => 'details', 'field_value' => 'Legacy employee details'],
    ]);

    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'Letter Approval HR',
        'email' => 'letter.approval.hr@example.com',
    ]);

    $this->actingAs($hrUser)
        ->get("/letter-requests/{$letterRequest->id}")
        ->assertInertia(fn ($page) => $page
            ->component('letter-requests/show')
            ->where('request.field_requires.to_address', true)
            ->where('request.field_requires.visa_designation', true)
            ->where('request.field_requires.destination_country', true)
            ->where('request.field_requires.purpose', true)
            ->where('request.destination_country', 'Germany')
            ->where('request.request_details.purpose', 'Employee purpose')
            ->where('request.request_details.destination_country', 'Germany')
            ->where('request.details', null));

    $this->actingAs($hrUser)
        ->from("/letter-requests/{$letterRequest->id}")
        ->post("/letter-requests/{$letterRequest->id}/approve", [
            'details' => 'HR confirmation for the German embassy.',
            'purpose' => 'Attempted employee-field edit',
        ])
        ->assertSessionHasErrors(['purpose']);

    expect($letterRequest->fresh()->status)->toBe('submitted');

    $this->actingAs($hrUser)
        ->from("/letter-requests/{$letterRequest->id}")
        ->post("/letter-requests/{$letterRequest->id}/approve", [
            'remarks' => 'Verified.',
        ])
        ->assertSessionHasErrors(['details']);

    $this->actingAs($hrUser)
        ->post("/letter-requests/{$letterRequest->id}/approve", [
            'details' => 'HR confirmation for the German embassy.',
            'remarks' => 'Verified.',
        ])
        ->assertRedirect();

    $approvedNoc = $letterRequest->fresh();
    expect($approvedNoc->status)->toBe('approved')
        ->and($approvedNoc->requestDetails()->where('field_key', 'purpose')->value('field_value'))
        ->toBe('Employee purpose')
        ->and($approvedNoc->requestDetails()->where('field_key', 'details')->value('field_name'))
        ->toBe('HR Provided Details')
        ->and($approvedNoc->requestDetails()->where('field_key', 'details')->value('field_value'))
        ->toBe('HR confirmation for the German embassy.');

    expect(app(LetterRequestService::class)->renderDocumentHtml($approvedNoc))
        ->toContain('To Embassy of Germany: Employee purpose / HR confirmation for the German embassy. for Employee #101 traveling to Germany');

    $salaryCertificateType = DocumentType::query()
        ->where('document_code', 'salary_certificate')
        ->firstOrFail();
    $salaryCertificate = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $salaryCertificateType->id,
        'document_title' => 'Salary Certificate Request',
        'status' => 'submitted',
    ]);

    $this->actingAs($hrUser)
        ->get("/letter-requests/{$salaryCertificate->id}")
        ->assertInertia(fn ($page) => $page
            ->where('request.field_requires.salary_amount', true)
            ->where('request.field_requires.currency', true));

    $this->actingAs($hrUser)
        ->post("/letter-requests/{$salaryCertificate->id}/approve", [
            'remarks' => 'Salary details verified.',
        ])
        ->assertRedirect();

    expect($salaryCertificate->fresh()->status)->toBe('approved');

    $salaryTransferType = DocumentType::query()
        ->where('document_code', 'salary_transfer_letter')
        ->firstOrFail();
    $salaryTransfer = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $salaryTransferType->id,
        'document_title' => 'Salary Transfer Request',
        'status' => 'submitted',
    ]);
    $salaryTransfer->requestDetails()->createMany([
        ['field_name' => 'Salary Amount', 'field_key' => 'salary_amount', 'field_value' => '8500.50'],
        ['field_name' => 'Currency', 'field_key' => 'currency', 'field_value' => 'AED'],
        ['field_name' => 'IBAN', 'field_key' => 'bank_iban', 'field_value' => 'AE070331234567890123456'],
        ['field_name' => 'Address', 'field_key' => 'to_address', 'field_value' => 'Bank of Example'],
        ['field_name' => 'Purpose', 'field_key' => 'purpose', 'field_value' => 'Employee transfer purpose'],
    ]);

    $this->actingAs($hrUser)
        ->get("/letter-requests/{$salaryTransfer->id}")
        ->assertInertia(fn ($page) => $page
            ->where('request.field_requires.salary_amount', true)
            ->where('request.field_requires.currency', true)
            ->where('request.field_requires.bank_iban', true)
            ->where('request.request_details.bank_iban', 'AE070331234567890123456'));

    $this->actingAs($hrUser)
        ->from("/letter-requests/{$salaryTransfer->id}")
        ->post("/letter-requests/{$salaryTransfer->id}/approve", [
            'salary_amount' => '10000',
            'details' => 'HR-authorized salary transfer.',
        ])
        ->assertSessionHasErrors(['salary_amount']);

    $this->actingAs($hrUser)
        ->post("/letter-requests/{$salaryTransfer->id}/approve", [
            'details' => 'HR-authorized salary transfer.',
        ])
        ->assertRedirect();

    expect($salaryTransfer->fresh()->status)->toBe('approved')
        ->and($salaryTransfer->fresh()->requestDetails()->where('field_key', 'details')->value('field_value'))
        ->toBe('HR-authorized salary transfer.')
        ->and($salaryTransfer->fresh()->requestDetails()->where('field_key', 'purpose')->value('field_value'))
        ->toBe('Employee transfer purpose');
});

test('salary letter templates use the currency request placeholder', function () {
    $salaryCertificateType = DocumentType::query()
        ->where('document_code', 'salary_certificate')
        ->firstOrFail();
    $salaryCertificateTemplate = DocumentTemplate::query()
        ->where('document_type_id', $salaryCertificateType->id)
        ->where('template_name', 'Standard Salary Certificate')
        ->firstOrFail();

    $salaryTransferType = DocumentType::query()
        ->where('document_code', 'salary_transfer_letter')
        ->firstOrFail();
    $salaryTransferTemplate = DocumentTemplate::query()
        ->where('document_type_id', $salaryTransferType->id)
        ->where('template_name', 'Standard Salary Transfer Letter')
        ->firstOrFail();

    expect($salaryCertificateTemplate->template_code)
        ->toContain('{{currency}} {{gross_salary}}')
        ->not->toContain('AED')
        ->and($salaryTransferTemplate->template_code)
        ->toContain('{{currency}} {{gross_salary}}')
        ->not->toContain('AED');
});
