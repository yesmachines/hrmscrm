<?php

use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveHistory;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveRequestDetail;
use App\Models\LeaveRequestFile;
use App\Models\LeaveType;
use App\Models\Organisation;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
    DB::table('employee_profiles')->delete();
    DB::table('leave_request_approvals')->delete();
    DB::table('leave_request_details')->delete();
    DB::table('leave_request_files')->delete();
    DB::table('leave_histories')->delete();
    DB::table('leave_requests')->delete();
    DB::table('leave_policies')->delete();
    DB::table('leave_balances')->delete();
    DB::table('leave_types')->delete();
    Organisation::query()->firstOrCreate(['id' => 1], ['org_name' => 'Default Org', 'short_name' => 'DEF', 'status' => 1]);
});

test('get leave detail returns full leave request with attachments, history, details, and approvals', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Leave User',
        'email' => 'leave.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $approver = SalesCrmUser::query()->create([
        'name' => 'Manager Jane',
        'email' => 'manager.jane@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-LEAVE-01',
        'designation' => 'Software Engineer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'requires_attachment' => false,
        'requires_approval' => true,
        'requires_handover' => true,
        'status' => 1,
    ]);

    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-01 09:00:00',
        'end_date' => '2026-10-05 18:00:00',
        'total_days' => 5,
        'remarks' => 'Annual family vacation',
        'status' => 'applied',
        'created_by' => $user->id,
    ]);

    LeaveRequestFile::query()->create([
        'leave_request_id' => $leaveRequest->id,
        'file_path' => 'leave-certificates/flight_ticket.pdf',
        'uploaded_by' => $user->id,
        'uploaded_date' => now(),
    ]);

    LeaveRequestDetail::query()->create([
        'leave_request_id' => $leaveRequest->id,
        'field_name' => 'Handover Description',
        'field_key' => 'handover_description',
        'field_value' => 'Handed over API tasks to Jane',
    ]);

    LeaveHistory::query()->create([
        'leave_request_id' => $leaveRequest->id,
        'action_type' => 'applied',
        'remarks' => 'Leave application submitted via API.',
        'done_by' => $user->id,
        'action_on' => now(),
    ]);

    LeaveRequestApproval::query()->create([
        'leave_request_id' => $leaveRequest->id,
        'approval_level' => 'Manager',
        'approver_id' => $approver->id,
        'remarks' => 'Recommended for approval',
        'approved_date' => now(),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson("/api/v1/leaves/{$leaveRequest->id}");

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('data.id', $leaveRequest->id)
        ->assertJsonPath('data.total_days', 5)
        ->assertJsonPath('data.status', 'applied')
        ->assertJsonPath('data.leave_type.leave_name', 'Annual Leave')
        ->assertJsonPath('data.files.0.file_path', 'leave-certificates/flight_ticket.pdf')
        ->assertJsonPath('data.details.0.field_key', 'handover_description')
        ->assertJsonPath('data.histories.0.action_type', 'applied')
        ->assertJsonPath('data.histories.0.done_by.name', 'Leave User')
        ->assertJsonPath('data.approvals.0.approver.name', 'Manager Jane');
});

test('get leave detail returns 404 if leave belongs to another employee', function () {
    $user1 = SalesCrmUser::query()->create([
        'name' => 'User One',
        'email' => 'user1@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee1 = Employee::query()->create([
        'user_id' => $user1->id,
        'emp_num' => 'EMP-001',
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $user2 = SalesCrmUser::query()->create([
        'name' => 'User Two',
        'email' => 'user2@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee2 = Employee::query()->create([
        'user_id' => $user2->id,
        'emp_num' => 'EMP-002',
        'designation' => 'QA',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK',
        'status' => 1,
    ]);

    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee2->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-02',
        'total_days' => 2,
        'status' => 'applied',
    ]);

    $token1 = $user1->createToken('test')->plainTextToken;

    $response = $this->withToken($token1)
        ->getJson("/api/v1/leaves/{$leaveRequest->id}");

    $response->assertNotFound()
        ->assertJsonPath('statusCode', 404);
});

test('leave meta returns single policy for each leave type with combined requires_attachment', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Meta User',
        'email' => 'meta.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-META-01',
        'designation' => 'Tester',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK',
        'is_paid' => true,
        'requires_attachment' => false,
        'requires_approval' => true,
        'max_days' => 90,
        'status' => 1,
    ]);

    $org = Organisation::query()->first();

    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $org->id,
        'full_pay_days' => 15,
        'half_pay_days' => 30,
        'no_pay_days' => 45,
        'requires_document_after_days' => 2,
        'requires_weekend_document' => true,
        'requires_attachment' => true,
        'carry_forward' => false,
        'encashment' => false,
        'probation_applicable' => true,
        'minimum_service_months' => 0,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/leaves/meta');

    $response->assertOk()
        ->assertJsonPath('data.leave_types.0.id', $leaveType->id)
        ->assertJsonPath('data.leave_types.0.requires_attachment', true)
        ->assertJsonPath('data.leave_types.0.policy.requires_document_after_days', 2)
        ->assertJsonPath('data.leave_types.0.policy.full_pay_days', 15);
});

test('apply leave fails when policy requires document after days and attachment is omitted', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Doc User',
        'email' => 'doc.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-DOC-01',
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => true,
        'status' => 1,
    ]);

    LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 15,
        'used' => 0,
        'balance' => 15,
    ]);

    $org = Organisation::query()->first();

    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $org->id,
        'full_pay_days' => 15,
        'half_pay_days' => 30,
        'no_pay_days' => 45,
        'requires_document_after_days' => 2,
        'requires_attachment' => false,
        'probation_applicable' => true,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // 3 days (> 2 days threshold) without certificate
    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-02', // Monday
        'end_date' => '2026-11-04',   // Wednesday (3 days)
        'total_days' => 3,
        'remarks' => 'Fever',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['certificate']);
});

test('apply leave fails when leave type max days is exceeded', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Max User',
        'email' => 'max.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-MAX-01',
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Casual Leave',
        'code' => 'CASUAL',
        'is_paid' => true,
        'max_days' => 3,
        'status' => 1,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-06',
        'total_days' => 5,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['total_days']);
});

test('sick leave for single mid-week day does not require certificate', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Sick Single User',
        'email' => 'sick.single@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-SS-01',
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $sickType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => true,
        'status' => 1,
    ]);

    LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickType->id,
        'year' => 2026,
        'allocated' => 15,
        'used' => 0,
        'balance' => 15,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // Single day on Wednesday (2026-11-04 is Wednesday)
    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $sickType->id,
        'start_date' => '2026-11-04',
        'end_date' => '2026-11-04',
        'total_days' => 1,
        'remarks' => 'Mild cold',
    ]);

    $response->assertCreated()
        ->assertJsonPath('statusCode', 201)
        ->assertJsonPath('message', 'Leave request submitted successfully.');
});

test('sick leave for 2 consecutive days requires certificate', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Sick Consecutive User',
        'email' => 'sick.consec@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-SC-01',
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $sickType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => true,
        'status' => 1,
    ]);

    LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickType->id,
        'year' => 2026,
        'allocated' => 15,
        'used' => 0,
        'balance' => 15,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // 2 consecutive days: Tuesday to Wednesday (2026-11-03 to 2026-11-04) without certificate
    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $sickType->id,
        'start_date' => '2026-11-03',
        'end_date' => '2026-11-04',
        'total_days' => 2,
        'remarks' => 'High fever',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['certificate']);

    // Now submit WITH certificate
    $file = UploadedFile::fake()->create('medical_cert.pdf', 200, 'application/pdf');
    $responseWithFile = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $sickType->id,
        'start_date' => '2026-11-03',
        'end_date' => '2026-11-04',
        'total_days' => 2,
        'remarks' => 'High fever',
        'certificate' => $file,
    ]);

    $responseWithFile->assertCreated()
        ->assertJsonPath('statusCode', 201);
});

test('sick leave combined with weekend requires certificate', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Sick Weekend User',
        'email' => 'sick.wknd@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-SW-01',
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $sickType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => true,
        'status' => 1,
    ]);

    LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickType->id,
        'year' => 2026,
        'allocated' => 15,
        'used' => 0,
        'balance' => 15,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // 1 day on Friday (2026-11-06 is Friday) without certificate -> should fail
    $responseFriday = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $sickType->id,
        'start_date' => '2026-11-06',
        'end_date' => '2026-11-06',
        'total_days' => 1,
        'remarks' => 'Migraine',
    ]);

    $responseFriday->assertStatus(422)
        ->assertJsonValidationErrors(['certificate']);

    // 1 day on Monday (2026-11-09 is Monday) without certificate -> should fail
    $responseMonday = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $sickType->id,
        'start_date' => '2026-11-09',
        'end_date' => '2026-11-09',
        'total_days' => 1,
        'remarks' => 'Stomach flu',
    ]);

    $responseMonday->assertStatus(422)
        ->assertJsonValidationErrors(['certificate']);
});

test('it stores signature image into leave_request_details and exposes signature_url', function () {
    Storage::fake('public');

    $user = SalesCrmUser::query()->create([
        'name' => 'Signature User',
        'email' => 'signature.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-SIG-01',
        'designation' => 'Developer',
        'division' => 'Engineering',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Casual Leave',
        'code' => 'CASUAL',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => true,
        'status' => 1,
    ]);

    LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 10,
        'used' => 0,
        'balance' => 10,
    ]);

    $token = $user->createToken('test')->plainTextToken;
    $fakeSignature = UploadedFile::fake()->image('signature.png', 200, 80);

    $response = $this->withToken($token)->post('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
        'total_days' => 1,
        'remarks' => 'Taking a day off with oath signature',
        'signature' => $fakeSignature,
        'declaration' => 'I hereby declare I will remain available for emergency queries.',
    ]);

    $response->assertStatus(201);
    $leaveId = $response->json('data.id');

    $this->assertDatabaseHas('leave_request_details', [
        'leave_request_id' => $leaveId,
        'field_name' => 'Signature',
        'field_key' => 'signature_path',
    ]);

    $this->assertDatabaseHas('leave_request_details', [
        'leave_request_id' => $leaveId,
        'field_name' => 'Declaration',
        'field_key' => 'declaration_text',
        'field_value' => 'I hereby declare I will remain available for emergency queries.',
    ]);

    $showResponse = $this->withToken($token)->getJson("/api/v1/leaves/{$leaveId}");
    $showResponse->assertStatus(200);

    expect($showResponse->json('data.signature_url'))->not->toBeNull();
    expect($showResponse->json('data.signature_url'))->toContain('leave-signatures');
});

test('leaves meta hides maternity leave for male employees and shows it for female employees', function () {
    $maleUser = SalesCrmUser::query()->create([
        'name' => 'John Male',
        'email' => 'john.male@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $maleEmployee = Employee::query()->create([
        'user_id' => $maleUser->id,
        'emp_num' => 'EMP-MALE-01',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $maleEmployee->id,
        'gender' => 'M',
    ]);

    $femaleUser = SalesCrmUser::query()->create([
        'name' => 'Jane Female',
        'email' => 'jane.female@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $femaleEmployee = Employee::query()->create([
        'user_id' => $femaleUser->id,
        'emp_num' => 'EMP-FEMALE-01',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $femaleEmployee->id,
        'gender' => 'F',
    ]);

    $annualLeave = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'requires_attachment' => false,
        'status' => 1,
        'gender' => null,
    ]);

    $maternityLeave = LeaveType::query()->create([
        'leave_name' => 'Maternity Leave',
        'code' => 'MATERNITY',
        'is_paid' => true,
        'requires_attachment' => false,
        'status' => 1,
        'gender' => 'female',
    ]);

    // Male token check: should NOT contain Maternity Leave
    $maleToken = $maleUser->createToken('test')->plainTextToken;
    $maleResponse = $this->withToken($maleToken)->getJson('/api/v1/leaves/meta');
    $maleResponse->assertOk();

    $maleLeaveTypeCodes = collect($maleResponse->json('data.leave_types'))->pluck('code')->all();
    expect($maleLeaveTypeCodes)->toContain('ANNUAL')
        ->and($maleLeaveTypeCodes)->not->toContain('MATERNITY');

    // Female token check: SHOULD contain Maternity Leave
    $femaleToken = $femaleUser->createToken('test')->plainTextToken;
    $this->app['auth']->forgetGuards();
    $femaleResponse = $this->withToken($femaleToken)->getJson('/api/v1/leaves/meta');
    $femaleResponse->assertOk();

    $femaleLeaveTypeCodes = collect($femaleResponse->json('data.leave_types'))->pluck('code')->all();
    expect($femaleLeaveTypeCodes)->toContain('ANNUAL')
        ->and($femaleLeaveTypeCodes)->toContain('MATERNITY');
});

test('applying maternity leave fails for male employee with validation error', function () {
    $maleUser = SalesCrmUser::query()->create([
        'name' => 'John Male Applier',
        'email' => 'john.applier@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $maleEmployee = Employee::query()->create([
        'user_id' => $maleUser->id,
        'emp_num' => 'EMP-MALE-02',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $maleEmployee->id,
        'gender' => 'M',
    ]);

    $maternityLeave = LeaveType::query()->create([
        'leave_name' => 'Maternity Leave',
        'code' => 'MATERNITY',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => false,
        'status' => 1,
    ]);

    $maleToken = $maleUser->createToken('test')->plainTextToken;
    $response = $this->withToken($maleToken)->postJson('/api/v1/leaves', [
        'leave_type_id' => $maternityLeave->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-10',
        'total_days' => 10,
        'remarks' => 'Maternity application by male',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['leave_type_id']);

    expect($response->json('errors.leave_type_id.0'))->toContain('only applicable for female employees');
});

test('applying maternity leave succeeds for female employee', function () {
    $femaleUser = SalesCrmUser::query()->create([
        'name' => 'Jane Female Applier',
        'email' => 'jane.applier@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $femaleEmployee = Employee::query()->create([
        'user_id' => $femaleUser->id,
        'emp_num' => 'EMP-FEMALE-02',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $femaleEmployee->id,
        'gender' => 'F',
    ]);

    $maternityLeave = LeaveType::query()->create([
        'leave_name' => 'Maternity Leave',
        'code' => 'MATERNITY',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => false,
        'status' => 1,
    ]);

    $femaleToken = $femaleUser->createToken('test')->plainTextToken;
    $response = $this->withToken($femaleToken)->postJson('/api/v1/leaves', [
        'leave_type_id' => $maternityLeave->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-10',
        'total_days' => 10,
        'remarks' => 'Maternity leave application',
        'due_date' => '2026-11-05',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $femaleEmployee->id,
        'leave_type_id' => $maternityLeave->id,
        'status' => 'applied',
    ]);
});

test('applying leave fails if selected handover person is also on leave during requested dates', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Applicant User',
        'email' => 'applicant.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $applicant = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-APP-01',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $colleagueUser = SalesCrmUser::query()->create([
        'name' => 'Colleague User',
        'email' => 'colleague.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $colleague = Employee::query()->create([
        'user_id' => $colleagueUser->id,
        'emp_num' => 'EMP-COL-01',
        'designation' => 'Senior Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'requires_attachment' => false,
        'requires_handover' => true,
        'allow_balance' => false,
        'status' => 1,
    ]);

    // Colleague already has an approved leave from Nov 10 to Nov 15
    LeaveRequest::query()->create([
        'employee_id' => $colleague->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-10 09:00:00',
        'end_date' => '2026-11-15 18:00:00',
        'total_days' => 5,
        'status' => 'approved',
        'created_by' => $colleagueUser->id,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // Applicant tries to request leave from Nov 12 to Nov 18 with colleague as handover person
    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-12',
        'end_date' => '2026-11-18',
        'total_days' => 6,
        'remarks' => 'Holiday vacation',
        'handover_person_id' => $colleague->id,
        'handover_description' => 'Handover sprint tasks',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['handover_person_id']);

    expect($response->json('errors.handover_person_id.0'))->toContain('also on leave');
});

test('applying leave succeeds when selected handover person is not on leave', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Applicant User 2',
        'email' => 'applicant2.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $applicant = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-APP-02',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $colleagueUser = SalesCrmUser::query()->create([
        'name' => 'Colleague User 2',
        'email' => 'colleague2.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $colleague = Employee::query()->create([
        'user_id' => $colleagueUser->id,
        'emp_num' => 'EMP-COL-02',
        'designation' => 'Senior Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'requires_attachment' => false,
        'requires_handover' => true,
        'allow_balance' => false,
        'status' => 1,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-12',
        'end_date' => '2026-11-18',
        'total_days' => 6,
        'remarks' => 'Holiday vacation',
        'handover_person_id' => $colleague->id,
        'handover_description' => 'Handover sprint tasks to available colleague',
    ]);

    $response->assertStatus(201);

    $leaveId = $response->json('data.id');
    $this->assertDatabaseHas('leave_request_details', [
        'leave_request_id' => $leaveId,
        'field_key' => 'handover_person_id',
        'field_value' => (string) $colleague->id,
    ]);
});

test('leaves meta hides pilgrimage leave for non-muslim employees and shows it for muslim employees', function () {
    $nonMuslimUser = SalesCrmUser::query()->create([
        'name' => 'John NonMuslim',
        'email' => 'john.nonmuslim@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $nonMuslimEmployee = Employee::query()->create([
        'user_id' => $nonMuslimUser->id,
        'emp_num' => 'EMP-NONM-01',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $nonMuslimEmployee->id,
        'religion' => 'Christian',
    ]);

    $muslimUser = SalesCrmUser::query()->create([
        'name' => 'Ahmed Muslim',
        'email' => 'ahmed.muslim@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $muslimEmployee = Employee::query()->create([
        'user_id' => $muslimUser->id,
        'emp_num' => 'EMP-MUS-01',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $muslimEmployee->id,
        'religion' => 'Muslim',
    ]);

    $annualLeave = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL-PLG',
        'is_paid' => true,
        'requires_attachment' => false,
        'status' => 1,
    ]);

    $pilgrimageLeave = LeaveType::query()->create([
        'leave_name' => 'Pilgrimage Leave',
        'code' => 'PILGRIMAGE',
        'is_paid' => true,
        'requires_attachment' => false,
        'status' => 1,
    ]);

    // Non-Muslim token check: should NOT contain Pilgrimage Leave
    $nonMuslimToken = $nonMuslimUser->createToken('test')->plainTextToken;
    $nonMuslimResponse = $this->withToken($nonMuslimToken)->getJson('/api/v1/leaves/meta');
    $nonMuslimResponse->assertOk();

    $nonMuslimCodes = collect($nonMuslimResponse->json('data.leave_types'))->pluck('code')->all();
    expect($nonMuslimCodes)->toContain('ANNUAL-PLG')
        ->and($nonMuslimCodes)->not->toContain('PILGRIMAGE');

    // Muslim token check: SHOULD contain Pilgrimage Leave
    $muslimToken = $muslimUser->createToken('test')->plainTextToken;
    $this->app['auth']->forgetGuards();
    $muslimResponse = $this->withToken($muslimToken)->getJson('/api/v1/leaves/meta');
    $muslimResponse->assertOk();

    $muslimCodes = collect($muslimResponse->json('data.leave_types'))->pluck('code')->all();
    expect($muslimCodes)->toContain('ANNUAL-PLG')
        ->and($muslimCodes)->toContain('PILGRIMAGE');
});

test('applying pilgrimage leave fails for non-muslim employee with validation error', function () {
    $nonMuslimUser = SalesCrmUser::query()->create([
        'name' => 'David NonMuslim',
        'email' => 'david.nonmuslim@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $nonMuslimEmployee = Employee::query()->create([
        'user_id' => $nonMuslimUser->id,
        'emp_num' => 'EMP-NONM-02',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $nonMuslimEmployee->id,
        'religion' => 'Christian',
    ]);

    $pilgrimageLeave = LeaveType::query()->create([
        'leave_name' => 'Pilgrimage Leave',
        'code' => 'PILGRIMAGE',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => false,
        'status' => 1,
    ]);

    $nonMuslimToken = $nonMuslimUser->createToken('test')->plainTextToken;
    $response = $this->withToken($nonMuslimToken)->postJson('/api/v1/leaves', [
        'leave_type_id' => $pilgrimageLeave->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-15',
        'total_days' => 15,
        'remarks' => 'Pilgrimage leave application',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['leave_type_id']);

    expect($response->json('errors.leave_type_id.0'))
        ->toBe('Pilgrimage Leave is only applicable for Muslim employees.');
});

test('applying pilgrimage leave succeeds for muslim employee', function () {
    $muslimUser = SalesCrmUser::query()->create([
        'name' => 'Fatima Muslim',
        'email' => 'fatima.muslim@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $muslimEmployee = Employee::query()->create([
        'user_id' => $muslimUser->id,
        'emp_num' => 'EMP-MUS-02',
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $muslimEmployee->id,
        'religion' => 'Islam',
    ]);

    $pilgrimageLeave = LeaveType::query()->create([
        'leave_name' => 'Pilgrimage Leave',
        'code' => 'PILGRIMAGE',
        'is_paid' => true,
        'requires_attachment' => false,
        'allow_balance' => false,
        'status' => 1,
    ]);

    $muslimToken = $muslimUser->createToken('test')->plainTextToken;
    $response = $this->withToken($muslimToken)->postJson('/api/v1/leaves', [
        'leave_type_id' => $pilgrimageLeave->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-15',
        'total_days' => 15,
        'remarks' => 'Hajj Pilgrimage leave',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $muslimEmployee->id,
        'leave_type_id' => $pilgrimageLeave->id,
        'status' => 'applied',
    ]);
});

test('leave meta returns religion and filters leave types by religion', function () {
    $christianUser = SalesCrmUser::query()->create([
        'name' => 'John Christian',
        'email' => 'john.christian@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $christianEmployee = Employee::query()->create([
        'user_id' => $christianUser->id,
        'emp_num' => 'EMP-CHR-01',
        'designation' => 'Designer',
        'division' => 'Creative',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $christianEmployee->id,
        'religion' => 'Christian',
    ]);

    $universalLeave = LeaveType::query()->create([
        'leave_name' => 'General Sick Leave',
        'code' => 'GEN_SICK',
        'is_paid' => true,
        'status' => 1,
        'religion' => null,
    ]);

    $muslimOnlyLeave = LeaveType::query()->create([
        'leave_name' => 'Islamic Pilgrimage',
        'code' => 'ISLAM_PILGRIM',
        'is_paid' => false,
        'status' => 1,
        'religion' => 'Muslim',
    ]);

    $christianOnlyLeave = LeaveType::query()->create([
        'leave_name' => 'Christian Retreat',
        'code' => 'CHR_RETREAT',
        'is_paid' => true,
        'status' => 1,
        'religion' => 'Christian',
    ]);

    $token = $christianUser->createToken('test')->plainTextToken;
    $response = $this->withToken($token)->getJson('/api/v1/leaves/meta');

    $response->assertOk()
        ->assertJsonPath('statusCode', 200);

    $leaveTypeCodes = collect($response->json('data.leave_types'))->pluck('code')->all();
    expect($leaveTypeCodes)->toContain('GEN_SICK')
        ->and($leaveTypeCodes)->toContain('CHR_RETREAT')
        ->and($leaveTypeCodes)->not->toContain('ISLAM_PILGRIM');

    $chrRetreat = collect($response->json('data.leave_types'))->firstWhere('code', 'CHR_RETREAT');
    expect($chrRetreat['religion'])->toBe('Christian');
});
