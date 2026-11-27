<?php

use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Organisation;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::connection('salescrm')->table('personal_access_tokens')->delete();
    DB::connection('salescrm')->table('employees')->delete();
    DB::connection('salescrm')->table('users')->delete();
    DB::table('leave_balances')->delete();
    DB::table('leave_policies')->delete();
    DB::table('leave_types')->delete();
    DB::table('organisations')->insertOrIgnore([
        'id' => 1,
        'org_name' => 'Default Org',
        'short_name' => 'DEF',
        'status' => 1,
    ]);

    $this->organisation = Organisation::query()->find(1);

    $this->compensatoryType = LeaveType::query()->create([
        'leave_name' => 'Compensatory Leave',
        'code' => 'COMPENSATORY',
        'is_paid' => true,
        'requires_approval' => true,
        'status' => 1,
    ]);

    $this->annualType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'requires_approval' => true,
        'status' => 1,
    ]);

    LeavePolicy::query()->create([
        'leave_type_id' => $this->compensatoryType->id,
        'organisation_id' => $this->organisation->id,
        'carry_forward_weeks' => 12,
        'allow_balance' => true,
    ]);

    $user = SalesCrmUser::factory()->create();
    $this->employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'EMP-COMP-01',
        'organisation_id' => $this->organisation->id,
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
    $this->employeeUser = $user;
});

test('only hr and admin can add leave balance via the api', function () {
    $payload = [
        'employee_id' => 0,
        'leave_type_id' => 0,
        'year' => now()->year,
        'allocated' => 1,
    ];

    $this->postJson(route('api.v1.hr.leave-balances.store'), $payload)->assertUnauthorized();

    $finance = createHrmsLoginUser('finance');
    Auth::forgetGuards();
    $this->withToken($finance->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.hr.leave-balances.store'), $payload)
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withToken($this->employeeUser->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.hr.leave-balances.store'), $payload)
        ->assertForbidden();

    foreach (['hr', 'admin'] as $role) {
        $user = createHrmsLoginUser($role);
        Auth::forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson(route('api.v1.hr.leave-balances.store'), [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $this->compensatoryType->id,
                'year' => now()->year,
                'allocated' => 1,
            ])
            ->assertCreated();
    }
});

test('hr adding compensatory leave balance sets validity from the policy carry_forward_weeks', function () {
    $hr = createHrmsLoginUser('hr');

    $response = $this->withToken($hr->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.hr.leave-balances.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->compensatoryType->id,
            'year' => now()->year,
            'allocated' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('data.allocated', 2)
        ->assertJsonPath('data.balance', 2);

    $balance = LeaveBalance::query()
        ->where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->compensatoryType->id)
        ->where('year', now()->year)
        ->firstOrFail();

    expect($balance->leave_validity)->not->toBeNull()
        ->and($balance->leave_validity->isSameDay(now()->addWeeks(12)))->toBeTrue();

    // Adding again increases the balance further and overwrites validity (single overall validity).
    $this->withToken($hr->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.hr.leave-balances.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->compensatoryType->id,
            'year' => now()->year,
            'allocated' => 3,
        ])
        ->assertCreated()
        ->assertJsonPath('data.allocated', 5)
        ->assertJsonPath('data.balance', 5);

    expect($balance->refresh()->leave_validity->isSameDay(now()->addWeeks(12)))->toBeTrue();
});

test('adding leave balance only sets validity when the policy configures carry_forward_weeks', function () {
    $hr = createHrmsLoginUser('hr');

    $this->withToken($hr->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.hr.leave-balances.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->annualType->id,
            'year' => now()->year,
            'allocated' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.allocated', 5);

    $balance = LeaveBalance::query()
        ->where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->annualType->id)
        ->where('year', now()->year)
        ->firstOrFail();

    expect($balance->leave_validity)->toBeNull();
});

test('adding balance for any leave type with carry_forward_weeks configured also sets validity', function () {
    LeavePolicy::query()->create([
        'leave_type_id' => $this->annualType->id,
        'organisation_id' => $this->organisation->id,
        'carry_forward_weeks' => 4,
        'allow_balance' => true,
    ]);
    $hr = createHrmsLoginUser('hr');

    $this->withToken($hr->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.hr.leave-balances.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->annualType->id,
            'year' => now()->year,
            'allocated' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.allocated', 5);

    $balance = LeaveBalance::query()
        ->where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->annualType->id)
        ->where('year', now()->year)
        ->firstOrFail();

    expect($balance->leave_validity)->not->toBeNull()
        ->and($balance->leave_validity->isSameDay(now()->addWeeks(4)))->toBeTrue();
});

test('add leave balance api validates required fields and quantity', function () {
    $hr = createHrmsLoginUser('hr');

    $this->withToken($hr->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.hr.leave-balances.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id', 'leave_type_id', 'year', 'allocated']);

    $this->withToken($hr->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.hr.leave-balances.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->compensatoryType->id,
            'year' => now()->year,
            'allocated' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['allocated']);
});

test('employee cannot apply compensatory leave once its validity has passed', function () {
    LeaveBalance::query()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->compensatoryType->id,
        'year' => now()->year,
        'allocated' => 2,
        'balance' => 2,
        'leave_validity' => now()->subDay(),
    ]);

    $date = now()->addDays(3);
    $response = $this->withToken($this->employeeUser->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.leaves.store'), [
            'leave_type_id' => $this->compensatoryType->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'request_type' => 'fullday',
        ])
        ->assertUnprocessable();

    expect($response->json('errors.leave_type_id.0'))->toContain('validity has expired');
});

test('employee can apply compensatory leave while its validity has not passed', function () {
    LeaveBalance::query()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->compensatoryType->id,
        'year' => now()->year,
        'allocated' => 2,
        'balance' => 2,
        'leave_validity' => now()->addWeeks(12),
    ]);

    $date = now()->addDays(3);
    $this->withToken($this->employeeUser->createToken('test')->plainTextToken)
        ->postJson(route('api.v1.leaves.store'), [
            'leave_type_id' => $this->compensatoryType->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'request_type' => 'fullday',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'applied');
});
