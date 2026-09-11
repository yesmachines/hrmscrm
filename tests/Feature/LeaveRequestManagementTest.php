<?php

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
