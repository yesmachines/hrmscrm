<?php

use App\Models\LeaveHistory;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveRequestDetail;
use App\Models\LeaveRequestFile;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
    DB::table('leave_request_approvals')->delete();
    DB::table('leave_request_details')->delete();
    DB::table('leave_request_files')->delete();
    DB::table('leave_histories')->delete();
    DB::table('leave_requests')->delete();
    DB::table('leave_policies')->delete();
    DB::table('leave_balances')->delete();
    DB::table('leave_types')->delete();
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
