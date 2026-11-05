<?php

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Services\LetterRequestService;
use App\Support\GeneratedDocumentExpiry;
use Carbon\Carbon;
use Database\Seeders\DocumentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();

    $this->seed(DocumentSeeder::class);
});

test('document type admin form exposes optional validity days and rejects values below one', function () {
    $admin = createHrmsLoginUser('admin');
    $category = DocumentCategory::query()->firstOrFail();

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('document-types.create'))
        ->assertInertia(fn ($page) => $page->component('document-types/create'));

    $this->actingAs($admin)
        ->from(route('document-types.create'))
        ->post(route('document-types.store'), [
            'category_id' => $category->id,
            'document_name' => 'Validity Test',
            'document_code' => 'validity_test',
            'requires_number' => 0,
            'requires_expiry' => 0,
            'requires_issue_date' => 0,
            'requires_attachments' => 0,
            'requires_uid' => 0,
            'requires_total_experience' => 0,
            'validity_days' => 0,
        ])
        ->assertSessionHasErrors('validity_days');
});

test('approved letter generation saves expiry days and stores the PDF publicly', function () {
    $this->travelTo(Carbon::parse('2026-11-03 10:00:00'));

    $employeeUser = SalesCrmUser::query()->create([
        'name' => 'Expiry Test Employee',
        'email' => 'expiry.test.employee@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $employeeUser->id,
        'emp_num' => 'EMP-EXPIRY-001',
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    $documentType = DocumentType::query()
        ->where('document_code', 'noc')
        ->firstOrFail();
    $documentType->update(['validity_days' => 3]);

    $template = DocumentTemplate::query()->create([
        'document_type_id' => $documentType->id,
        'template_name' => 'Expiry Test Template',
        'template_code' => 'To {{to_address}}: {{purpose}} for {{employee_name}}',
        'status' => 1,
    ]);

    $document = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $documentType->id,
        'document_template_id' => $template->id,
        'document_title' => 'NOC Expiry Test',
        'status' => 'submitted',
    ]);
    $document->requestDetails()->createMany([
        ['field_name' => 'Address', 'field_key' => 'to_address', 'field_value' => 'Embassy'],
        ['field_name' => 'Purpose', 'field_key' => 'purpose', 'field_value' => 'Visa application'],
    ]);

    (new LetterRequestService)->approve($document, [
        'to_address' => 'Embassy',
        'visa_designation' => 'Analyst',
        'purpose' => 'Visa application',
    ], 1);

    $document->refresh();
    $file = $document->files()->latest('id')->firstOrFail();

    expect($document->status)->toBe('approved')
        ->and($document->expiry_date?->format('Y-m-d'))->toBe('2026-11-06')
        ->and($file->uploaded_date?->format('Y-m-d H:i:s'))->toBe('2026-11-03 10:00:00');
    Storage::disk('public')->assertExists($file->file_path);
    Storage::disk('local')->assertMissing($file->file_path);

    $employeeToken = $employeeUser->createToken('expiry-test')->plainTextToken;
    $publicFileUrl = Storage::disk('public')->url($file->file_path);
    $this->withToken($employeeToken)
        ->getJson("/api/v1/documents/letters/{$document->id}")
        ->assertOk()
        ->assertJsonPath('data.file_url', $publicFileUrl)
        ->assertJsonPath('data.download_url', $publicFileUrl);

    $this->travelTo(Carbon::parse('2026-11-07 10:00:00'));

    expect($document->fresh()->status)->toBe('approved')
        ->and(GeneratedDocumentExpiry::isExpired($document->fresh()->load('documentType.category')))->toBeTrue();

    $this->withToken($employeeToken)
        ->getJson("/api/v1/documents/letters/{$document->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'Approved')
        ->assertJsonPath('data.is_expired', true)
        ->assertJsonPath('data.file_url', null)
        ->assertJsonPath('data.download_url', null);

    $uploadedDocumentType = DocumentType::query()
        ->where('document_code', 'passport')
        ->firstOrFail();
    $uploadedDocument = EmployeeDocument::query()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $uploadedDocumentType->id,
        'document_title' => 'Expired Passport',
        'status' => 'expired',
    ]);
    $uploadedPath = 'employee_documents/expired-passport.pdf';
    Storage::disk('public')->put($uploadedPath, 'test pdf');
    $uploadedDocument->files()->create([
        'version_no' => '1.0',
        'file_path' => $uploadedPath,
        'uploaded_date' => Carbon::now(),
        'change_notes' => 'Initial upload',
    ]);

    $this->withToken($employeeToken)
        ->getJson("/api/v1/documents/{$uploadedDocument->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'expired')
        ->assertJsonPath('data.is_expired', false)
        ->assertJsonPath('data.files.0.file_url', Storage::disk('public')->url($uploadedPath));

    $hrUser = createHrmsLoginUser('hr', [
        'name' => 'Expiry Test HR',
        'email' => 'expiry.test.hr@example.com',
    ]);
    $this->actingAs($hrUser)
        ->get("/letter-requests/{$document->id}")
        ->assertInertia(fn ($page) => $page
            ->where('request.status', 'approved')
            ->where('request.is_expired', true)
            ->where('request.file_url', null)
            ->where('request.download_url', null)
            ->where('request.rendered_html', null));

    $this->actingAs($hrUser)
        ->get(route('employee-documents.file', $document))
        ->assertForbidden();

    $this->actingAs($hrUser)
        ->get(route('employee-documents.show', $document))
        ->assertInertia(fn ($page) => $page
            ->component('employee-documents/show')
            ->where('document.status', 'approved')
            ->where('document.is_expired', true)
            ->where('document.file_url', null)
            ->where('document.rendered_html', null));
});

test('rejected letter requests are not assigned a generated document expiry', function () {
    $documentType = DocumentType::query()
        ->where('document_code', 'noc')
        ->firstOrFail();
    $documentType->update(['validity_days' => 3]);

    $document = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $documentType->id,
        'document_title' => 'Rejected NOC Request',
        'status' => 'submitted',
    ]);

    (new LetterRequestService)->reject($document, 'Request rejected', 1);

    expect($document->fresh()->status)->toBe('rejected')
        ->and($document->fresh()->expiry_date)->toBeNull()
        ->and($document->fresh()->files()->exists())->toBeFalse();
});

test('blank validity days use the generated file date for the three-month fallback', function () {
    $documentType = DocumentType::query()
        ->where('document_code', 'noc')
        ->firstOrFail();
    $document = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $documentType->id,
        'document_title' => 'Legacy NOC Request',
        'status' => 'approved',
    ]);
    $document->forceFill([
        'created_at' => Carbon::parse('2025-06-01 10:00:00'),
        'updated_at' => Carbon::parse('2025-06-01 10:00:00'),
    ])->save();
    $document->files()->create([
        'version_no' => '1.0',
        'file_path' => 'letters/generated-noc.pdf',
        'uploaded_date' => Carbon::parse('2026-10-15 10:00:00'),
        'change_notes' => 'Generated by HR upon approval',
    ]);

    $legacyDocument = EmployeeDocument::query()->create([
        'employee_id' => 101,
        'document_type_id' => $documentType->id,
        'document_title' => 'Legacy NOC Request',
        'status' => 'approved',
    ]);
    $legacyDocument->forceFill([
        'created_at' => Carbon::parse('2026-06-01 10:00:00'),
        'updated_at' => Carbon::parse('2026-06-01 10:00:00'),
    ])->save();
    $legacyDocument->files()->create([
        'version_no' => '1.0',
        'file_path' => 'letters/legacy-noc.pdf',
        'uploaded_date' => null,
        'change_notes' => 'Generated by HR upon approval',
    ]);

    $this->travelTo(Carbon::parse('2026-11-03 10:00:00'));

    expect(GeneratedDocumentExpiry::isExpired($document->fresh()->load('documentType.category', 'files')))->toBeFalse();

    expect($document->fresh()->status)->toBe('approved')
        ->and(GeneratedDocumentExpiry::isExpired(
            $legacyDocument->fresh()->load('documentType.category', 'files'),
        ))->toBeTrue();

    $this->travelTo(Carbon::parse('2027-01-16 10:00:00'));

    expect(GeneratedDocumentExpiry::isExpired($document->fresh()->load('documentType.category', 'files')))->toBeTrue();
});

test('old letter requests without generated PDFs are not expired', function () {
    $documentType = DocumentType::query()
        ->where('document_code', 'noc')
        ->firstOrFail();

    foreach (['submitted', 'rejected'] as $status) {
        $document = EmployeeDocument::query()->create([
            'employee_id' => 101,
            'document_type_id' => $documentType->id,
            'document_title' => 'Old '.ucfirst($status).' NOC Request',
            'status' => $status,
        ]);
        $document->forceFill([
            'created_at' => Carbon::parse('2025-01-01 10:00:00'),
            'updated_at' => Carbon::parse('2025-01-01 10:00:00'),
        ])->save();

        expect(GeneratedDocumentExpiry::isExpired(
            $document->fresh()->load('documentType.category', 'files'),
        ))->toBeFalse();
    }
});
