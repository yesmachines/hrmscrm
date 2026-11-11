<?php

use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveHistory;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveRequestFile;
use App\Models\LeaveType;
use App\Models\Organisation;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('hr user can view the apply leave page', function () {
    $hrUser = createHrmsLoginUser('hr');

    $this->actingAs($hrUser)
        ->withoutVite()
        ->get(route('leave-requests.create'))
        ->assertOk();
});

test('hr user can apply leave on behalf of employee with applied status', function () {
    $hrUser = createHrmsLoginUser('hr');

    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Software Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Casual Leave',
        'code' => 'CL',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-16',
            'total_days' => 2,
            'status' => 'applied',
            'remarks' => 'Family urgent matter.',
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $leaveRequest = LeaveRequest::query()
        ->where('employee_id', $employee->id)
        ->where('leave_type_id', $leaveType->id)
        ->first();

    expect($leaveRequest)->not->toBeNull()
        ->and($leaveRequest->status)->toBe('applied')
        ->and((float) $leaveRequest->total_days)->toBe(2.0)
        ->and($leaveRequest->created_by)->toBe($hrUser->id);

    $history = LeaveHistory::query()
        ->where('leave_request_id', $leaveRequest->id)
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->action_type)->toBe('applied')
        ->and($history->done_by)->toBe($hrUser->id)
        ->and($history->remarks)->toContain('Applied by HR')
        ->and(LeaveRequestApproval::query()->where('leave_request_id', $leaveRequest->id)->exists())->toBeFalse();
});

test('hr automatically approves leave types that do not require approval without creating an approval row', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Software Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Personal Leave',
        'code' => 'PERSONAL-NO-APPROVAL',
        'is_paid' => 1,
        'requires_approval' => 0,
        'status' => 1,
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

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-16',
            'total_days' => 2,
            'status' => 'applied',
            'remarks' => 'Personal matter.',
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $leaveRequest = LeaveRequest::query()
        ->where('employee_id', $employee->id)
        ->where('leave_type_id', $leaveType->id)
        ->firstOrFail();
    $history = LeaveHistory::query()
        ->where('leave_request_id', $leaveRequest->id)
        ->firstOrFail();

    expect($leaveRequest->status)->toBe('approved')
        ->and($history->action_type)->toBe('approved')
        ->and($history->remarks)->toContain('does not require approval')
        ->and(LeaveRequestApproval::query()->where('leave_request_id', $leaveRequest->id)->exists())->toBeFalse();

    $balance->refresh();
    expect((float) $balance->used)->toBe(3.0)
        ->and((float) $balance->balance)->toBe(7.0)
        ->and((float) $balance->pending)->toBe(0.0);
});

test('hr leave requests enforce one-time, annual, and minimum-service policy rules', function () {
    $hrUser = createHrmsLoginUser('hr');
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Test Organisation', 'short_name' => 'TEST', 'status' => 1],
    );
    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'organisation_id' => $organisation->id,
        'joining_date' => '2026-08-01',
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);

    $onceLeaveType = LeaveType::query()->create([
        'leave_name' => 'One Time Leave',
        'code' => 'HR-ONCE',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $onceLeaveType->id,
        'organisation_id' => $organisation->id,
        'allow_once' => true,
        'allow_balance' => false,
        'probation_applicable' => true,
    ]);
    foreach (['cancelled', 'rejected'] as $status) {
        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $onceLeaveType->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-01',
            'request_type' => 'fullday',
            'total_days' => 1,
            'status' => $status,
        ]);
    }

    $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $onceLeaveType->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
            'total_days' => 1,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.index'));

    $this->actingAs($hrUser)
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $onceLeaveType->id,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-20',
            'total_days' => 1,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.create'))
        ->assertSessionHasErrors(['leave_type_id']);

    $annualLeaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Limit Leave',
        'code' => 'HR-ANNUAL-LIMIT',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $annualLeaveType->id,
        'organisation_id' => $organisation->id,
        'annual_limit' => 1,
        'allow_balance' => false,
        'probation_applicable' => true,
    ]);
    LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $annualLeaveType->id,
        'start_date' => '2026-01-02',
        'end_date' => '2026-01-02',
        'request_type' => 'fullday',
        'total_days' => 1,
        'status' => 'applied',
    ]);

    $this->actingAs($hrUser)
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $annualLeaveType->id,
            'start_date' => '2026-12-02',
            'end_date' => '2026-12-02',
            'total_days' => 1,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.create'))
        ->assertSessionHasErrors(['total_days']);

    $serviceLeaveType = LeaveType::query()->create([
        'leave_name' => 'Service Leave',
        'code' => 'HR-SERVICE',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $serviceLeaveType->id,
        'organisation_id' => $organisation->id,
        'minimum_service_months' => 6,
        'allow_balance' => false,
        'probation_applicable' => true,
    ]);

    $this->actingAs($hrUser)
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $serviceLeaveType->id,
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-15',
            'total_days' => 1,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.create'))
        ->assertSessionHasErrors(['leave_type_id']);
});

test('hr leave request documents and handover follow the policy duration threshold', function () {
    Storage::fake('public');
    $hrUser = createHrmsLoginUser('hr');
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Test Organisation', 'short_name' => 'TEST', 'status' => 1],
    );
    $employee = Employee::query()->create([
        'user_id' => SalesCrmUser::factory()->create()->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'organisation_id' => $organisation->id,
        'designation' => 'Developer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $handoverEmployee = Employee::query()->create([
        'user_id' => SalesCrmUser::factory()->create()->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'organisation_id' => $organisation->id,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Threshold Leave',
        'code' => 'HR-THRESHOLD',
        'requires_approval' => true,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $organisation->id,
        'annual_limit' => 12,
        'allow_balance' => false,
        'requires_document_after_days' => 2,
        'requires_attachment' => true,
        'requires_handover' => true,
        'probation_applicable' => true,
    ]);

    $this->actingAs($hrUser)
        ->withoutVite()
        ->get(route('leave-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->component('leave-requests/create')
            ->where('employees', fn ($employees) => collect($employees)->contains(
                fn ($employeeData) => $employeeData['id'] === $employee->id
                    && $employeeData['organisation_id'] === $organisation->id,
            ))
            ->where('leaveTypes', fn ($leaveTypes) => collect($leaveTypes)->contains(
                fn ($leaveTypeData) => $leaveTypeData['id'] === $leaveType->id
                    && $leaveTypeData['policies'][0]['annual_limit'] === 12
                    && $leaveTypeData['policies'][0]['requires_document_after_days'] === 2,
            ))
        );

    $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-03',
            'total_days' => 2,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.index'));

    $this->actingAs($hrUser)
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-07',
            'total_days' => 3,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.create'))
        ->assertSessionHasErrors(['handover_person_id']);

    $this->actingAs($hrUser)
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-07',
            'total_days' => 3,
            'status' => 'applied',
            'handover_person_id' => $handoverEmployee->id,
            'handover_description' => 'Cover project work.',
        ])
        ->assertRedirect(route('leave-requests.create'))
        ->assertSessionHasErrors(['certificate']);

    $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-07',
            'total_days' => 3,
            'status' => 'applied',
            'handover_person_id' => $handoverEmployee->id,
            'handover_description' => 'Cover project work.',
            'certificate' => UploadedFile::fake()->create('supporting-document.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('leave-requests.index'));
});

test('hr user can create a half-day leave request and half-day requests must use one date', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Designer',
        'division' => 'Creative',
        'status' => 1,
        'has_report' => false,
    ]);
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Personal Leave',
        'code' => 'PERSONAL',
        'is_paid' => 1,
        'status' => 1,
    ]);

    $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'request_type' => 'firsthalf',
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-15',
            'total_days' => 1,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.index'));

    $leaveRequest = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($leaveRequest->request_type)->toBe('firsthalf')
        ->and((float) $leaveRequest->total_days)->toBe(0.5);

    $this->actingAs($hrUser)
        ->from(route('leave-requests.edit', $leaveRequest))
        ->put(route('leave-requests.update', $leaveRequest), [
            'leave_type_id' => $leaveType->id,
            'request_type' => 'secondhalf',
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-16',
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.edit', $leaveRequest))
        ->assertSessionHasErrors(['request_type']);
});

test('hr user can apply and directly approve leave on behalf of employee with quota deduction', function () {
    $hrUser = createHrmsLoginUser('hr');

    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Product Manager',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'AL',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);

    $balance = LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 20,
        'carried_forward' => 0,
        'used' => 2,
        'balance' => 18,
    ]);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'total_days' => 3,
            'status' => 'approved',
            'remarks' => 'Pre-approved conference travel.',
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $leaveRequest = LeaveRequest::query()
        ->where('employee_id', $employee->id)
        ->first();

    expect($leaveRequest)->not->toBeNull()
        ->and($leaveRequest->status)->toBe('approved');

    $approval = LeaveRequestApproval::query()
        ->where('leave_request_id', $leaveRequest->id)
        ->first();

    expect($approval)->not->toBeNull()
        ->and($approval->approver_id)->toBe($hrUser->id);

    $balance->refresh();
    expect((float) $balance->used)->toBe(5.0)
        ->and((float) $balance->balance)->toBe(15.0);
});

test('hr user can attach supporting document when applying leave on behalf of employee', function () {
    Storage::fake('public');
    $hrUser = createHrmsLoginUser('hr');
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Test Organisation', 'short_name' => 'TEST', 'status' => 1],
    );

    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'UI Designer',
        'division' => 'Creative',
        'organisation_id' => $organisation->id,
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SL',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);

    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $organisation->id,
        'requires_attachment' => true,
    ]);

    $file = UploadedFile::fake()->create('medical_slip.pdf', 200, 'application/pdf');

    $this->actingAs($hrUser)
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'total_days' => 2,
            'status' => 'applied',
            'remarks' => 'Doctor recommendation.',
        ])
        ->assertRedirect(route('leave-requests.create'))
        ->assertSessionHasErrors(['certificate']);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'total_days' => 2,
            'status' => 'applied',
            'remarks' => 'Doctor recommendation.',
            'certificate' => $file,
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $leaveRequest = LeaveRequest::query()
        ->where('employee_id', $employee->id)
        ->first();

    $requestFile = LeaveRequestFile::query()
        ->where('leave_request_id', $leaveRequest->id)
        ->first();

    expect($requestFile)->not->toBeNull()
        ->and($requestFile->uploaded_by)->toBe($hrUser->id);

    Storage::disk('public')->assertExists($requestFile->file_path);
});

test('apply leave page provides employees with gender and leave types with gender', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Developer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'gender' => 'M',
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Maternity Leave',
        'code' => 'MATERNITY',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
        'gender' => 'female',
    ]);

    $this->actingAs($hrUser)
        ->withoutVite()
        ->get(route('leave-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-requests/create')
            ->has('employees')
            ->has('leaveTypes')
            ->has('activeLeaves')
            ->where('employees', fn ($emps) => collect($emps)->contains(fn ($e) => $e['id'] === $employee->id && $e['gender'] === 'M'))
            ->where('leaveTypes', fn ($types) => collect($types)->contains(fn ($t) => $t['id'] === $leaveType->id && $t['gender'] === 'female'))
        );
});

test('hr user cannot apply maternity leave for male employee', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $maleEmployee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Dev',
        'division' => 'Engineering',
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
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
        'gender' => 'female',
    ]);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $maleEmployee->id,
            'leave_type_id' => $maternityLeave->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'total_days' => 10,
            'status' => 'applied',
            'remarks' => 'Attempting maternity for male',
        ]);

    $response->assertSessionHasErrors([
        'leave_type_id' => 'Maternity Leave is only applicable for female employees.',
    ]);
});

test('hr user can apply maternity leave for female employee', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $femaleEmployee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Dev',
        'division' => 'Engineering',
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
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
        'gender' => 'female',
    ]);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $femaleEmployee->id,
            'leave_type_id' => $maternityLeave->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'total_days' => 10,
            'status' => 'applied',
            'remarks' => 'Maternity leave approved by doctor',
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $femaleEmployee->id,
        'leave_type_id' => $maternityLeave->id,
        'status' => 'applied',
    ]);
});

test('hr user cannot apply leave when selected handover person is also on leave', function () {
    $hrUser = createHrmsLoginUser('hr');
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Test Organisation', 'short_name' => 'TEST', 'status' => 1],
    );
    $salesUser = SalesCrmUser::factory()->create();

    $applicant = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'QA Engineer',
        'division' => 'Engineering',
        'organisation_id' => $organisation->id,
        'status' => 1,
        'has_report' => false,
    ]);

    $colleagueUser = SalesCrmUser::factory()->create();
    $colleague = Employee::query()->create([
        'user_id' => $colleagueUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'QA Lead',
        'division' => 'Engineering',
        'organisation_id' => $organisation->id,
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL-HO',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $organisation->id,
        'requires_handover' => true,
    ]);

    // Colleague has existing active leave from Oct 10 to Oct 16
    LeaveRequest::query()->create([
        'employee_id' => $colleague->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-16',
        'total_days' => 7,
        'status' => 'applied',
        'created_by' => $hrUser->id,
    ]);

    // Applicant tries to apply with colleague as handover from Oct 12 to Oct 18 (overlapping)
    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $applicant->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'total_days' => 7,
            'status' => 'applied',
            'remarks' => 'Vacation leave',
            'handover_person_id' => $colleague->id,
            'handover_description' => 'QA pipeline coverage',
        ]);

    $response->assertSessionHasErrors(['handover_person_id']);
});

test('hr user can apply leave with valid handover person and it records handover details', function () {
    $hrUser = createHrmsLoginUser('hr');
    $organisation = Organisation::query()->firstOrCreate(
        ['id' => 1],
        ['org_name' => 'Test Organisation', 'short_name' => 'TEST', 'status' => 1],
    );
    $salesUser = SalesCrmUser::factory()->create();

    $applicant = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'DevOps Engineer',
        'division' => 'Infrastructure',
        'organisation_id' => $organisation->id,
        'status' => 1,
        'has_report' => false,
    ]);

    $colleagueUser = SalesCrmUser::factory()->create();
    $colleague = Employee::query()->create([
        'user_id' => $colleagueUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Cloud Architect',
        'division' => 'Infrastructure',
        'organisation_id' => $organisation->id,
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL-VALID-HO',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);
    LeavePolicy::query()->create([
        'leave_type_id' => $leaveType->id,
        'organisation_id' => $organisation->id,
        'requires_handover' => true,
    ]);

    $this->actingAs($hrUser)
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $applicant->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'total_days' => 7,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.create'))
        ->assertSessionHasErrors(['handover_person_id']);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $applicant->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'total_days' => 7,
            'status' => 'applied',
            'remarks' => 'Vacation leave',
            'handover_person_id' => $colleague->id,
            'handover_description' => 'Infrastructure monitoring coverage',
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $leave = LeaveRequest::query()
        ->where('employee_id', $applicant->id)
        ->where('leave_type_id', $leaveType->id)
        ->first();

    expect($leave)->not->toBeNull();

    $this->assertDatabaseHas('leave_request_details', [
        'leave_request_id' => $leave->id,
        'field_key' => 'handover_person_id',
        'field_value' => (string) $colleague->id,
    ]);

    $this->assertDatabaseHas('leave_request_details', [
        'leave_request_id' => $leave->id,
        'field_key' => 'handover_description',
        'field_value' => 'Infrastructure monitoring coverage',
    ]);
});

test('hr user cannot apply pilgrimage leave for non-muslim employee', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'religion' => 'Hindu',
    ]);

    $pilgrimLeave = LeaveType::query()->create([
        'leave_name' => 'Pilgrimage Leave',
        'code' => 'PILGRIMAGE',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
        'religion' => 'Islam',
    ]);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $pilgrimLeave->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'total_days' => 15,
            'status' => 'applied',
            'remarks' => 'Pilgrimage request',
        ]);

    $response->assertSessionHasErrors(['leave_type_id']);
});

test('hr user can apply pilgrimage leave for muslim employee', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Executive',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'religion' => 'Muslim',
    ]);

    $pilgrimLeave = LeaveType::query()->create([
        'leave_name' => 'Pilgrimage Leave',
        'code' => 'PILGRIMAGE',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
        'religion' => 'Islam',
    ]);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $pilgrimLeave->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'total_days' => 15,
            'status' => 'applied',
            'remarks' => 'Pilgrimage request',
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $employee->id,
        'leave_type_id' => $pilgrimLeave->id,
        'status' => 'applied',
    ]);
});

test('hr user can view edit leave page', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Developer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Casual Leave',
        'code' => 'CL-EDIT',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-03',
        'total_days' => 3,
        'status' => 'applied',
        'created_by' => $hrUser->id,
    ]);

    $this->actingAs($hrUser)
        ->withoutVite()
        ->get(route('leave-requests.edit', $leave))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-requests/edit')
            ->has('leaveRequest')
            ->has('employees')
            ->has('leaveTypes')
            ->where('leaveRequest.request_type', 'fullday')
            ->where('leaveRequest.leave_type.leave_name', 'Casual Leave')
        );
});

test('hr cannot change the leave type while editing a leave request', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Developer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);

    $originalLeaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL-LOCKED',
        'is_paid' => true,
        'status' => 1,
    ]);

    $replacementLeaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SICK-LOCKED',
        'is_paid' => true,
        'status' => 1,
    ]);

    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $originalLeaveType->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-02',
        'total_days' => 2,
        'status' => 'applied',
        'created_by' => $hrUser->id,
    ]);

    $this->actingAs($hrUser)
        ->from(route('leave-requests.edit', $leaveRequest))
        ->put(route('leave-requests.update', $leaveRequest), [
            'employee_id' => $employee->id,
            'leave_type_id' => $replacementLeaveType->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'total_days' => 2,
            'status' => 'applied',
        ])
        ->assertRedirect(route('leave-requests.edit', $leaveRequest))
        ->assertSessionHasErrors([
            'leave_type_id' => 'Leave type cannot be changed after the request is created.',
        ]);

    expect($leaveRequest->fresh()->leave_type_id)->toBe($originalLeaveType->id);
});

test('hr user can cancel an approved leave request and balance is refunded', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Analyst',
        'division' => 'Finance',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Paid Time Off',
        'code' => 'PTO-CANCEL',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);

    $balance = LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 20,
        'carried_forward' => 0,
        'used' => 5,
        'balance' => 15,
    ]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-07',
        'total_days' => 3,
        'status' => 'approved',
        'created_by' => $hrUser->id,
    ]);

    $response = $this->actingAs($hrUser)
        ->post(route('leave-requests.cancel', $leave), [
            'remarks' => 'Employee cancelled trip due to project deadline.',
        ]);

    $response->assertRedirect();

    $leave->refresh();
    expect($leave->status)->toBe('cancelled');

    $balance->refresh();
    // 3 days should be refunded: used = 5 - 3 = 2, balance = 15 + 3 = 18
    expect((float) $balance->used)->toBe(2.0)
        ->and((float) $balance->balance)->toBe(18.0);

    $this->assertDatabaseHas('leave_histories', [
        'leave_request_id' => $leave->id,
        'action_type' => 'cancelled',
        'done_by' => $hrUser->id,
    ]);
});

test('hr user editing date range of approved leave adjusts leave balance for duration increase and decrease', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Engineer',
        'division' => 'Engineering',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'AL-DATES',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);

    $balance = LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 20,
        'carried_forward' => 0,
        'used' => 3,
        'balance' => 17,
    ]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-12',
        'total_days' => 3,
        'status' => 'approved',
        'created_by' => $hrUser->id,
    ]);

    // 1. Extend leave from 3 days to 5 days (increase duration)
    $response = $this->actingAs($hrUser)
        ->put(route('leave-requests.update', $leave), [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-14',
            'total_days' => 5,
            'status' => 'approved',
            'remarks' => 'Extended leave by 2 days.',
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $leave->refresh();
    expect((float) $leave->total_days)->toBe(5.0)
        ->and($leave->end_date->format('Y-m-d'))->toBe('2026-10-14');

    $balance->refresh();
    // Additional 2 days deducted: used = 3 + 2 = 5, balance = 17 - 2 = 15
    expect((float) $balance->used)->toBe(5.0)
        ->and((float) $balance->balance)->toBe(15.0);

    // 2. Reduce leave from 5 days to 2 days (decrease duration)
    $response2 = $this->actingAs($hrUser)
        ->put(route('leave-requests.update', $leave), [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-11',
            'total_days' => 2,
            'status' => 'approved',
            'remarks' => 'Shortened leave back to 2 days.',
        ]);

    $response2->assertRedirect(route('leave-requests.index'));

    $leave->refresh();
    expect((float) $leave->total_days)->toBe(2.0);

    $balance->refresh();
    // 3 days refunded: used = 5 - 3 = 2, balance = 15 + 3 = 18
    expect((float) $balance->used)->toBe(2.0)
        ->and((float) $balance->balance)->toBe(18.0);
});

test('hr user editing status from applied to approved deducts leave balance', function () {
    $hrUser = createHrmsLoginUser('hr');
    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Coordinator',
        'division' => 'Operations',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SL-APPROVE',
        'is_paid' => 1,
        'requires_approval' => 1,
        'status' => 1,
    ]);

    $balance = LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 12,
        'carried_forward' => 0,
        'used' => 1,
        'balance' => 11,
    ]);

    $leave = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-20',
        'end_date' => '2026-10-22',
        'total_days' => 3,
        'status' => 'applied',
        'created_by' => $hrUser->id,
    ]);

    // Update to approved
    $response = $this->actingAs($hrUser)
        ->put(route('leave-requests.update', $leave), [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-22',
            'total_days' => 3,
            'status' => 'approved',
            'remarks' => 'Approved by HR manager.',
        ]);

    $response->assertRedirect(route('leave-requests.index'));

    $leave->refresh();
    expect($leave->status)->toBe('approved');

    $balance->refresh();
    // 3 days deducted: used = 1 + 3 = 4, balance = 11 - 3 = 8
    expect((float) $balance->used)->toBe(4.0)
        ->and((float) $balance->balance)->toBe(8.0);
});
