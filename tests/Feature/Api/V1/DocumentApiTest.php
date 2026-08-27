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
        'document_number' => '784-1990-1234567-1',
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

    $response = $this->withToken($token)->postJson('/api/v1/documents/letters', [
        'document_type_id' => $nocType->id,
        'purpose' => 'Employment Visa Process',
        'details' => 'Requesting NOC for embassy submission.',
        'to_address' => 'Embassy of Germany',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('statusCode', 201);

    $letter = EmployeeDocument::query()->where('employee_id', $employee->id)->first();
    expect($letter)->not->toBeNull();

    // Check letter details endpoint
    $detailsResponse = $this->withToken($token)->getJson("/api/v1/documents/letters/{$letter->id}");
    $detailsResponse->assertOk()
        ->assertJsonPath('data.purpose', 'Employment Visa Process');
});
