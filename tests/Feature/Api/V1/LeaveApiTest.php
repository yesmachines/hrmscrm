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
use Illuminate\Support\Facades\Auth;
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
    DB::table('organisations')->insertOrIgnore([
        'id' => 1,
        'org_name' => 'Default Org',
        'short_name' => 'DEF',
        'status' => 1,
    ]);
});

test('leave submission validates emergency and travel contact numbers', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Leave User',
        'email' => 'leave.contact-validation@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-CONTACT-01',
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'emergency_contact_no' => '123-456',
        'travel_contact_no' => '+971 50 123 4567',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['emergency_contact_no', 'travel_contact_no']);
});

test('leave validation endpoint rejects marital status and reports missing maternity due date', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Maternity Validation User',
        'email' => 'leave.maternity-validation@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-MAT-VALIDATE',
        'organisation_id' => 1,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'gender' => 'F',
        'marital_status' => 'single',
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Maternity Leave',
        'code' => 'MATERNITY',
        'gender' => 'female',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
    ]);
    $token = $user->createToken('test')->plainTextToken;
    $payload = [
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDay()->toDateString(),
    ];

    $this->withToken($token)
        ->postJson('/api/v1/leaves/validate', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['leave_type_id']);

    EmployeeProfile::query()->where('employee_id', $employee->id)->update(['marital_status' => 'married']);

    $this->withToken($token)
        ->postJson('/api/v1/leaves/validate', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['due_date']);

    expect(LeaveRequest::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('parental leave submission accepts an expected birth date after leave starts', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Parental Leave User',
        'email' => 'leave.parental@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-PARENTAL-01',
        'organisation_id' => 1,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'marital_status' => 'married',
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Parental Leave',
        'code' => 'PARENTAL',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'annual_limit' => 5,
        'allow_balance' => false,
    ]);
    $leaveStartDate = now()->addWeek()->startOfDay();
    $childBirthDate = $leaveStartDate->copy()->addDays(3)->toDateString();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $leaveStartDate->toDateString(),
        'end_date' => $leaveStartDate->toDateString(),
        'request_type' => 'fullday',
        'child_birth_date' => $leaveStartDate->copy()->addMonths(6)->toDateString(),
    ])->assertUnprocessable();

    expect(LeaveRequest::query()->where('employee_id', $employee->id)->exists())->toBeFalse();

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $leaveStartDate->toDateString(),
        'end_date' => $leaveStartDate->toDateString(),
        'request_type' => 'fullday',
        'child_birth_date' => $childBirthDate,
    ])->assertCreated();

    $leaveRequest = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();

    expect($leaveRequest->details()->where('field_key', 'child_birth_date')->value('field_value'))
        ->toBe($childBirthDate);
});

test('paternity leave submission requires and stores child birth date', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Paternity Leave User',
        'email' => 'leave.paternity@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-PATERNITY-01',
        'organisation_id' => 1,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Paternity Leave',
        'code' => 'PATERNITY',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
    ]);
    $leaveStartDate = now()->addWeek()->startOfDay();
    $token = $user->createToken('test')->plainTextToken;
    $payload = [
        'leave_type_id' => $leaveType->id,
        'start_date' => $leaveStartDate->toDateString(),
        'end_date' => $leaveStartDate->toDateString(),
    ];

    $this->withToken($token)
        ->postJson('/api/v1/leaves', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['child_birth_date']);

    expect(LeaveRequest::query()->where('employee_id', $employee->id)->exists())->toBeFalse();

    $childBirthDate = $leaveStartDate->copy()->addDays(3)->toDateString();
    $this->withToken($token)
        ->postJson('/api/v1/leaves', [
            ...$payload,
            'child_birth_date' => $childBirthDate,
        ])
        ->assertCreated();

    $leaveRequest = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($leaveRequest->details()->where('field_key', 'child_birth_date')->value('field_value'))
        ->toBe($childBirthDate);
});

test('leave submission stores half-day request type and duration', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Half Day User',
        'email' => 'leave.half-day@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-HALF-01',
        'designation' => 'Engineer',
        'division' => 'Technology',
        'organisation_id' => 1,
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Personal Leave',
        'code' => 'PERSONAL',
        'is_paid' => true,
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'request_type' => 'secondhalf',
        'start_date' => '2026-09-15',
        'end_date' => '2026-09-15',
        'total_days' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.request_type', 'secondhalf')
        ->assertJsonPath('data.total_days', 0.5)
        ->assertJsonPath('data.status', 'applied');

    $leaveRequest = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($leaveRequest->request_type)->toBe('secondhalf')
        ->and((float) $leaveRequest->total_days)->toBe(0.5);

    expect(LeaveRequestApproval::query()->where('leave_request_id', $leaveRequest->id)->exists())->toBeFalse();
});

test('employee leave type without approval is auto-approved and deducts balance without an approval row', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Auto Approved User',
        'email' => 'leave.auto-approved@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-AUTO-01',
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Personal Leave',
        'code' => 'PERSONAL-AUTO',
        'is_paid' => true,
        'requires_approval' => false,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => true,
    ]);
    $balance = LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 10,
        'carried_forward' => 0,
        'used' => 1,
        'balance' => 9,
        'pending' => 0,
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-12-11',
        'end_date' => '2026-12-14',
        'request_type' => 'fullday',
        'total_days' => 4,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.request_type', 'fullday')
        ->assertJsonPath('data.total_days', 2)
        ->assertJsonPath('data.status', 'approved');

    $leaveRequest = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();
    expect((float) $leaveRequest->total_days)->toBe(2.0);

    $history = DB::table('leave_histories')
        ->where('leave_request_id', $leaveRequest->id)
        ->first();

    expect($leaveRequest->status)->toBe('approved')
        ->and($history?->action_type)->toBe('approved')
        ->and($history?->remarks)->toContain('does not require approval')
        ->and(LeaveRequestApproval::query()->where('leave_request_id', $leaveRequest->id)->exists())->toBeFalse();

    $balance->refresh();
    expect((float) $balance->used)->toBe(3.0)
        ->and((float) $balance->balance)->toBe(7.0)
        ->and((float) $balance->pending)->toBe(0.0);
});

test('employee can update their own applied leave request without changing its leave type', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Leave Update User',
        'email' => 'leave.update@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-UPDATE-01',
        'organisation_id' => 1,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Personal Leave',
        'code' => 'PERSONAL-UPDATE',
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
    ]);
    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
        'request_type' => 'fullday',
        'total_days' => 1,
        'remarks' => 'Original reason',
        'status' => 'applied',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/leaves/{$leaveRequest->id}", [
            'start_date' => '2026-12-03',
            'end_date' => '2026-12-04',
            'remarks' => 'Updated reason',
        ])
        ->assertOk()
        ->assertJsonPath('data.leave_type_id', $leaveType->id)
        ->assertJsonPath('data.status', 'applied')
        ->assertJsonPath('data.total_days', 2)
        ->assertJsonPath('data.remarks', 'Updated reason');

    expect($leaveRequest->fresh()->start_date->toDateString())->toBe('2026-12-03')
        ->and($leaveRequest->fresh()->leave_type_id)->toBe($leaveType->id);
});

test('employee cannot change leave type or edit a non-applied leave request through the api', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Leave Edit Restrictions User',
        'email' => 'leave.edit-restrictions@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-EDIT-RESTRICTIONS',
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $firstType = LeaveType::query()->create([
        'leave_name' => 'First Leave',
        'code' => 'FIRST-EDIT',
        'status' => 1,
    ]);
    $secondType = LeaveType::query()->create([
        'leave_name' => 'Second Leave',
        'code' => 'SECOND-EDIT',
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $firstType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
    ]);
    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $firstType->id,
        'start_date' => '2026-12-10',
        'end_date' => '2026-12-10',
        'total_days' => 1,
        'status' => 'applied',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/leaves/{$leaveRequest->id}", [
            'leave_type_id' => $secondType->id,
            'start_date' => '2026-12-11',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['leave_type_id']);

    expect($leaveRequest->fresh()->leave_type_id)->toBe($firstType->id)
        ->and($leaveRequest->fresh()->start_date->toDateString())->toBe('2026-12-10');

    foreach (['approved', 'cancelled', 'rejected'] as $status) {
        $leaveRequest->update(['status' => $status]);

        $this->withToken($token)
            ->patchJson("/api/v1/leaves/{$leaveRequest->id}", [
                'start_date' => '2026-12-12',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
});

test('leave request update is not capped by a removed max-days field', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Leave Limit Update User',
        'email' => 'leave.limit-update@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-LIMIT-UPDATE',
        'organisation_id' => 1,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Limited Leave',
        'code' => 'LIMITED-UPDATE',
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
    ]);
    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-12-20',
        'end_date' => '2026-12-20',
        'total_days' => 1,
        'status' => 'applied',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/leaves/{$leaveRequest->id}", [
            'start_date' => '2026-12-20',
            'end_date' => '2026-12-21',
        ])
        ->assertOk()
        ->assertJsonPath('data.total_days', 2);

    expect($leaveRequest->fresh()->end_date->toDateString())->toBe('2026-12-21');
});

test('leave application enforces annual and one-time limits from leave policy', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Policy Limit User',
        'email' => 'leave.policy-limits@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-POLICY-LIMITS',
        'designation' => 'Engineer',
        'division' => 'Technology',
        'organisation_id' => 1,
        'status' => 1,
        'has_report' => false,
    ]);
    $onceLeaveType = LeaveType::query()->create([
        'leave_name' => 'One Time Leave',
        'code' => 'ONCE',
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $onceLeaveType->id,
        'organisation_id' => 1,
        'allow_once' => true,
        'allow_balance' => false,
    ]);
    LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $onceLeaveType->id,
        'start_date' => '2025-01-02 00:00:00',
        'end_date' => '2025-01-02 23:59:59',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $token = $user->createToken('test')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/leaves/meta')
        ->assertOk()
        ->assertJsonPath('data.leave_types.0.annual_limit', null)
        ->assertJsonPath('data.leave_types.0.allow_once', true)
        ->assertJsonPath('data.leave_types.0.allow_balance', false);

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $onceLeaveType->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
        'total_days' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['leave_type_id']);

    $annualLeaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Limit Leave',
        'code' => 'ANNUAL-LIMIT',
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $annualLeaveType->id,
        'organisation_id' => 1,
        'annual_limit' => 1,
        'allow_balance' => false,
    ]);
    LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $annualLeaveType->id,
        'start_date' => '2026-01-02 00:00:00',
        'end_date' => '2026-01-02 23:59:59',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $annualLeaveType->id,
        'start_date' => '2026-12-02',
        'end_date' => '2026-12-02',
        'total_days' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['total_days']);
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
        'requires_approval' => true,
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

test('leave meta returns policy-owned document and handover requirements', function () {
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
        'organisation_id' => 1,
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK',
        'is_paid' => true,
        'requires_approval' => true,
        'status' => 1,
    ]);

    $org = Organisation::query()->first();

    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $org->id,
        'requires_document_after_days' => 2,
        'requires_weekend_document' => true,
        'requires_attachment' => true,
        'requires_handover' => true,
        'encashment' => false,
        'probation_applicable' => true,
        'minimum_service_months' => 0,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/leaves/meta');

    $response->assertOk()
        ->assertJsonPath('data.leave_types.0.id', $leaveType->id)
        ->assertJsonPath('data.leave_types.0.policy.requires_attachment', true)
        ->assertJsonPath('data.leave_types.0.policy.requires_handover', true)
        ->assertJsonPath('data.leave_types.0.policy.requires_document_after_days', 2)
        ->assertJsonMissingPath('data.leave_types.0.policy.full_pay_days')
        ->assertJsonMissingPath('data.leave_types.0.policy.carry_forward')
        ->assertJsonMissingPath('data.leave_types.0.requires_attachment')
        ->assertJsonMissingPath('data.leave_types.0.requires_handover')
        ->assertJsonMissingPath('data.leave_types.0.max_days');
});

test('leave metadata and validation use only the employee organisation policy', function () {
    $firstOrganisation = Organisation::query()->create([
        'org_name' => 'First Leave Policy Org',
        'short_name' => 'FIRST-LEAVE',
        'status' => 1,
    ]);
    $secondOrganisation = Organisation::query()->create([
        'org_name' => 'Second Leave Policy Org',
        'short_name' => 'SECOND-LEAVE',
        'status' => 1,
    ]);
    $unconfiguredOrganisation = Organisation::query()->create([
        'org_name' => 'Unconfigured Leave Org',
        'short_name' => 'NO-POLICY',
        'status' => 1,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK-ORG-SCOPE',
        'is_paid' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $firstOrganisation->id,
        'annual_limit' => 5,
        'allow_once' => true,
        'allow_balance' => false,
        'requires_attachment' => true,
        'requires_handover' => true,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $secondOrganisation->id,
        'annual_limit' => 8,
        'allow_once' => false,
        'allow_balance' => false,
        'requires_attachment' => false,
        'requires_handover' => false,
    ]);

    $createEmployee = function (string $email, string $employeeNumber, int $organisationId): array {
        $user = SalesCrmUser::query()->create([
            'name' => 'Policy Scope User',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'emp_num' => $employeeNumber,
            'organisation_id' => $organisationId,
            'designation' => 'Developer',
            'division' => 'Technology',
            'status' => 1,
            'has_report' => false,
        ]);

        return [$user, $employee];
    };

    [$firstUser, $firstEmployee] = $createEmployee(
        'first-policy-scope@example.com',
        'EMP-POLICY-ORG-1',
        $firstOrganisation->id,
    );
    [$secondUser, $secondEmployee] = $createEmployee(
        'second-policy-scope@example.com',
        'EMP-POLICY-ORG-2',
        $secondOrganisation->id,
    );
    [$unconfiguredUser, $unconfiguredEmployee] = $createEmployee(
        'unconfigured-policy-scope@example.com',
        'EMP-POLICY-ORG-3',
        $unconfiguredOrganisation->id,
    );

    expect($firstOrganisation->id)->not->toBe($secondOrganisation->id)
        ->and($firstEmployee->organisation_id)->toBe($firstOrganisation->id)
        ->and($secondEmployee->organisation_id)->toBe($secondOrganisation->id);

    $this->withToken($firstUser->createToken('test')->plainTextToken)
        ->getJson('/api/v1/leaves/meta')
        ->assertOk()
        ->assertJsonPath('data.employee.id', $firstEmployee->id)
        ->assertJsonPath('data.leave_types.0.annual_limit', 5)
        ->assertJsonPath('data.leave_types.0.allow_once', true)
        ->assertJsonPath('data.leave_types.0.policy.requires_attachment', true);

    Auth::forgetGuards();

    $this->withToken($secondUser->createToken('test')->plainTextToken)
        ->getJson('/api/v1/leaves/meta')
        ->assertOk()
        ->assertJsonPath('data.employee.id', $secondEmployee->id)
        ->assertJsonPath('data.leave_types.0.annual_limit', 8)
        ->assertJsonPath('data.leave_types.0.allow_once', false)
        ->assertJsonPath('data.leave_types.0.policy.requires_attachment', false);

    Auth::forgetGuards();

    $this->withToken($unconfiguredUser->createToken('test')->plainTextToken)
        ->getJson('/api/v1/leaves/meta')
        ->assertOk()
        ->assertJsonPath('data.employee.id', $unconfiguredEmployee->id)
        ->assertJsonPath('data.leave_types.0.id', $leaveType->id)
        ->assertJsonPath('data.leave_types.0.annual_limit', null)
        ->assertJsonPath('data.leave_types.0.allow_once', false)
        ->assertJsonPath('data.leave_types.0.allow_balance', true)
        ->assertJsonPath('data.leave_types.0.policy', null);

    LeaveBalance::query()->create([
        'employee_id' => $unconfiguredEmployee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 5,
        'carried_forward' => 0,
        'used' => 0,
        'balance' => 5,
        'pending' => 0,
    ]);

    $this->withToken($unconfiguredUser->createToken('test')->plainTextToken)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-07',
            'end_date' => '2026-11-07',
            'total_days' => 1,
        ])
        ->assertCreated();
});

test('handover requirement applies only when duration exceeds its configured threshold', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Threshold Handover User',
        'email' => 'threshold.handover@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-HANDOVER-THRESHOLD',
        'organisation_id' => 1,
        'designation' => 'Developer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $handoverEmployee = Employee::query()->create([
        'user_id' => SalesCrmUser::factory()->create()->id,
        'emp_num' => 'EMP-HANDOVER-COLLEAGUE',
        'organisation_id' => 1,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Threshold Handover Leave',
        'code' => 'HANDOVER-THRESHOLD',
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
        'requires_document_after_days' => 2,
        'requires_handover' => true,
        'probation_applicable' => true,
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-03',
        'total_days' => 2,
    ])->assertCreated();

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-07',
        'total_days' => 3,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['handover_person_id']);

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-07',
        'total_days' => 3,
        'handover_person_id' => $handoverEmployee->id,
        'handover_description' => 'Cover development work',
    ])
        ->assertCreated();
});

test('document threshold does not require an attachment when the attachment flag is false', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Optional Document User',
        'email' => 'optional.document@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);
    Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-OPTIONAL-DOCUMENT',
        'organisation_id' => 1,
        'designation' => 'Developer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Optional Document Leave',
        'code' => 'OPTIONAL-DOCUMENT',
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
        'requires_document_after_days' => 2,
        'requires_attachment' => false,
        'probation_applicable' => true,
    ]);

    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-04',
            'total_days' => 3,
        ])
        ->assertCreated();
});

test('leave application requires an attachment when its policy requires one', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Attachment Policy User',
        'email' => 'attachment.policy@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-ATTACHMENT-POLICY',
        'designation' => 'Developer',
        'division' => 'Technology',
        'organisation_id' => 1,
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Personal Leave',
        'code' => 'PERSONAL-ATTACHMENT-POLICY',
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
        'requires_attachment' => true,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-02',
            'total_days' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['certificate']);
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
        'organisation_id' => 1,
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Personal Leave',
        'code' => 'PERSONAL-DOCUMENT-THRESHOLD',
        'is_paid' => true,
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
        'requires_document_after_days' => 2,
        'requires_attachment' => true,
        'probation_applicable' => true,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-03',
        'total_days' => 2,
    ])
        ->assertCreated();

    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-07',
        'total_days' => 3,
        'remarks' => 'Fever',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['certificate']);
});

test('apply leave is not capped by a max days field on the leave type', function () {
    $user = SalesCrmUser::query()->create([
        'name' => 'Max User',
        'email' => 'max.user@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-MAX-01',
        'organisation_id' => 1,
        'designation' => 'Dev',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Casual Leave',
        'code' => 'CASUAL',
        'is_paid' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-06',
        'total_days' => 5,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.total_days', 5);
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

test('sick leave without a matching policy has no hard-coded certificate requirement', function () {
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

    // 2 consecutive days: Tuesday to Wednesday (2026-11-03 to 2026-11-04)
    $response = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $sickType->id,
        'start_date' => '2026-11-03',
        'end_date' => '2026-11-04',
        'total_days' => 2,
        'remarks' => 'High fever',
    ]);

    $response->assertCreated()
        ->assertJsonPath('statusCode', 201);
});

test('sick leave near a weekend has no hard-coded certificate requirement', function () {
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

    // 1 day on Friday (2026-11-06 is Friday)
    $responseFriday = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $sickType->id,
        'start_date' => '2026-11-06',
        'end_date' => '2026-11-06',
        'total_days' => 1,
        'remarks' => 'Migraine',
    ]);

    $responseFriday->assertCreated()
        ->assertJsonPath('statusCode', 201);

    // 1 day on Monday (2026-11-09 is Monday)
    $responseMonday = $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $sickType->id,
        'start_date' => '2026-11-09',
        'end_date' => '2026-11-09',
        'total_days' => 1,
        'remarks' => 'Stomach flu',
    ]);

    $responseMonday->assertCreated()
        ->assertJsonPath('statusCode', 201);
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
        'marital_status' => 'married',
    ]);

    $annualLeave = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'status' => 1,
        'gender' => null,
    ]);

    $maternityLeave = LeaveType::query()->create([
        'leave_name' => 'Maternity Leave',
        'code' => 'MATERNITY',
        'is_paid' => true,
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
        'status' => 1,
        'gender' => 'female',
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $maternityLeave->id,
        'organisation_id' => 1,
        'allow_balance' => false,
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

test('applying maternity leave succeeds for married female employee and stores expected due date', function () {
    $femaleUser = SalesCrmUser::query()->create([
        'name' => 'Jane Female Applier',
        'email' => 'jane.applier@example.com',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $femaleEmployee = Employee::query()->create([
        'user_id' => $femaleUser->id,
        'emp_num' => 'EMP-FEMALE-02',
        'organisation_id' => 1,
        'designation' => 'Developer',
        'division' => 'Tech',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $femaleEmployee->id,
        'gender' => 'F',
        'marital_status' => 'married',
    ]);

    $maternityLeave = LeaveType::query()->create([
        'leave_name' => 'Maternity Leave',
        'code' => 'MATERNITY',
        'is_paid' => true,
        'status' => 1,
        'gender' => 'female',
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $maternityLeave->id,
        'organisation_id' => 1,
        'allow_balance' => false,
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
    $leaveRequest = LeaveRequest::query()->where('employee_id', $femaleEmployee->id)->firstOrFail();
    expect($leaveRequest->details()->where('field_key', 'due_date')->value('field_value'))
        ->toBe('2026-11-05');
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
        'organisation_id' => 1,
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
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
        'requires_handover' => true,
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
        'organisation_id' => 1,
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
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => 1,
        'allow_balance' => false,
        'requires_handover' => true,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-18',
            'total_days' => 6,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['handover_person_id']);

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
        'status' => 1,
    ]);

    $pilgrimageLeave = LeaveType::query()->create([
        'leave_name' => 'Pilgrimage Leave',
        'code' => 'PILGRIMAGE',
        'is_paid' => true,
        'status' => 1,
        'religion' => 'Islam',
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
        'organisation_id' => 1,
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
        'status' => 1,
        'religion' => 'Islam',
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $pilgrimageLeave->id,
        'organisation_id' => 1,
        'allow_balance' => false,
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
        ->toBe('Pilgrimage Leave is only applicable for Islam employees.');
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
        'organisation_id' => 1,
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
        'status' => 1,
        'religion' => 'Islam',
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $pilgrimageLeave->id,
        'organisation_id' => 1,
        'allow_balance' => false,
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
