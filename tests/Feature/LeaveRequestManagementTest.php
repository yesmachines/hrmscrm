<?php

use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveHistory;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveRequestFile;
use App\Models\LeaveType;
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
        'requires_attachment' => 0,
        'requires_approval' => 1,
        'max_days' => 14,
        'annual_limit' => 14,
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
        ->and($history->remarks)->toContain('Applied by HR');
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
        'requires_attachment' => 0,
        'requires_approval' => 1,
        'max_days' => 21,
        'annual_limit' => 21,
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

    $salesUser = SalesCrmUser::factory()->create();

    $employee = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'UI Designer',
        'division' => 'Creative',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Sick Leave',
        'code' => 'SL',
        'is_paid' => 1,
        'requires_attachment' => 1,
        'requires_approval' => 1,
        'max_days' => 15,
        'annual_limit' => 15,
        'status' => 1,
    ]);

    $file = UploadedFile::fake()->create('medical_slip.pdf', 200, 'application/pdf');

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
        'requires_attachment' => 0,
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
        'requires_attachment' => 0,
        'requires_approval' => 1,
        'status' => 1,
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
        'requires_attachment' => 0,
        'requires_approval' => 1,
        'status' => 1,
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
    $salesUser = SalesCrmUser::factory()->create();

    $applicant = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'QA Engineer',
        'division' => 'Engineering',
        'status' => 1,
        'has_report' => false,
    ]);

    $colleagueUser = SalesCrmUser::factory()->create();
    $colleague = Employee::query()->create([
        'user_id' => $colleagueUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'QA Lead',
        'division' => 'Engineering',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL-HO',
        'is_paid' => 1,
        'requires_attachment' => 0,
        'requires_approval' => 1,
        'requires_handover' => 1,
        'status' => 1,
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
    $salesUser = SalesCrmUser::factory()->create();

    $applicant = Employee::query()->create([
        'user_id' => $salesUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'DevOps Engineer',
        'division' => 'Infrastructure',
        'status' => 1,
        'has_report' => false,
    ]);

    $colleagueUser = SalesCrmUser::factory()->create();
    $colleague = Employee::query()->create([
        'user_id' => $colleagueUser->id,
        'emp_num' => fake()->unique()->bothify('EMP-#####'),
        'designation' => 'Cloud Architect',
        'division' => 'Infrastructure',
        'status' => 1,
        'has_report' => false,
    ]);

    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL-VALID-HO',
        'is_paid' => 1,
        'requires_attachment' => 0,
        'requires_approval' => 1,
        'requires_handover' => 1,
        'status' => 1,
    ]);

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
        'requires_attachment' => 0,
        'requires_approval' => 1,
        'status' => 1,
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
        'requires_attachment' => 0,
        'requires_approval' => 1,
        'status' => 1,
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
