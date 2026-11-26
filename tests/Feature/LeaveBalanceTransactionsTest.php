<?php

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Services\LeaveBalanceService;
use App\Services\LeaveDurationCalculator;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function createLeaveBalanceLedgerEmployee(): Employee
{
    return Employee::query()->create([
        'user_id' => SalesCrmUser::factory()->create()->id,
        'emp_num' => fake()->unique()->bothify('BAL-#####'),
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
}

function createLeaveBalanceLedgerType(string $code = 'LEDGER'): LeaveType
{
    return LeaveType::query()->create([
        'leave_name' => 'Ledger Leave',
        'code' => fake()->unique()->bothify($code.'-#####'),
        'requires_approval' => true,
        'status' => 1,
    ]);
}

function createLeaveBalanceLedgerRequest(
    Employee $employee,
    LeaveType $leaveType,
    string $status = 'applied',
    string $startDate = '2026-12-31',
    string $endDate = '2027-01-01',
    float $totalDays = 2,
): LeaveRequest {
    return LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'request_type' => 'fullday',
        'total_days' => $totalDays,
        'status' => $status,
    ]);
}

test('leave days and balance movements are split and recorded by calendar year', function () {
    $employee = createLeaveBalanceLedgerEmployee();
    $leaveType = createLeaveBalanceLedgerType();
    $balanceService = app(LeaveBalanceService::class);
    $daysByYear = app(LeaveDurationCalculator::class)->allocateByCalendarYear(
        Carbon\Carbon::parse('2026-12-31'),
        Carbon\Carbon::parse('2027-01-01'),
        'fullday',
    );

    expect($daysByYear)->toBe([2026 => 1.0, 2027 => 1.0]);

    foreach ([2026, 2027] as $year) {
        $balanceService->createOpeningBalance([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $year,
            'allocated' => 10,
        ], null, 'Opening entitlement');
    }

    $leaveRequest = createLeaveBalanceLedgerRequest($employee, $leaveType);
    $balanceService->recordNewRequest($leaveRequest, $daysByYear, null, 'Applied');

    foreach ([2026, 2027] as $year) {
        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->firstOrFail();

        expect($balance->pending)->toBe(1.0)
            ->and($balance->balance)->toBe(9.0);
    }

    $balanceService->transitionRequest($leaveRequest, 'approved', $daysByYear, null, 'Approved');
    $leaveRequest->update(['status' => 'approved']);

    foreach ([2026, 2027] as $year) {
        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->firstOrFail();

        expect($balance->pending)->toBe(0.0)
            ->and($balance->used)->toBe(1.0)
            ->and($balance->balance)->toBe(9.0);
    }

    $balanceService->transitionRequest($leaveRequest, 'cancelled', [], null, 'Cancelled');
    $leaveRequest->update(['status' => 'cancelled']);

    foreach ([2026, 2027] as $year) {
        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->firstOrFail();

        expect($balance->pending)->toBe(0.0)
            ->and($balance->used)->toBe(0.0)
            ->and($balance->balance)->toBe(10.0);
    }

    expect(LeaveBalanceTransaction::query()
        ->where('leave_request_id', $leaveRequest->id)
        ->pluck('transaction_type')
        ->all())->toEqualCanonicalizing([
            'pending_increased',
            'pending_increased',
            'pending_decreased',
            'pending_decreased',
            'used_increased',
            'used_increased',
            'used_decreased',
            'used_decreased',
        ]);
});

test('leave application is rejected when a required calendar year balance is missing', function () {
    $employee = createLeaveBalanceLedgerEmployee();
    $leaveType = createLeaveBalanceLedgerType();
    $balanceService = app(LeaveBalanceService::class);

    $balanceService->createOpeningBalance([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 10,
    ], null, 'Opening entitlement');

    expect(fn () => $balanceService->assertAvailable(
        $employee,
        $leaveType,
        null,
        [2026 => 1.0, 2027 => 1.0],
    ))->toThrow(ValidationException::class);
});

test('hr leave submission records pending balance movements and a separate request history row', function () {
    $hrUser = createHrmsLoginUser('hr');
    $employee = createLeaveBalanceLedgerEmployee();
    $leaveType = createLeaveBalanceLedgerType();
    $balanceService = app(LeaveBalanceService::class);

    foreach ([2026, 2027] as $year) {
        $balanceService->createOpeningBalance([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $year,
            'allocated' => 10,
        ], (int) $hrUser->id, 'Opening entitlement');
    }

    $this->actingAs($hrUser)
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-12-31',
            'end_date' => '2027-01-01',
            'request_type' => 'fullday',
            'total_days' => 2,
            'status' => 'applied',
            'remarks' => 'Cross-year leave request.',
        ])
        ->assertRedirect(route('leave-requests.index'));

    $leaveRequest = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();

    expect($leaveRequest->histories()->where('action_type', 'applied')->exists())->toBeTrue()
        ->and(LeaveBalanceTransaction::query()
            ->where('leave_request_id', $leaveRequest->id)
            ->pluck('transaction_type')
            ->all())->toEqualCanonicalizing(['pending_increased', 'pending_increased']);
});

test('a request approved on creation consumes used balance without pending balance', function () {
    $employee = createLeaveBalanceLedgerEmployee();
    $leaveType = createLeaveBalanceLedgerType();
    $balanceService = app(LeaveBalanceService::class);

    $balanceService->createOpeningBalance([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'allocated' => 10,
    ], null, 'Opening entitlement');

    $leaveRequest = createLeaveBalanceLedgerRequest(
        $employee,
        $leaveType,
        'approved',
        '2026-12-31',
        '2026-12-31',
        1,
    );
    $balanceService->recordNewRequest($leaveRequest, [2026 => 1.0], null, 'Directly approved');

    $balance = LeaveBalance::query()
        ->where('employee_id', $employee->id)
        ->where('leave_type_id', $leaveType->id)
        ->where('year', 2026)
        ->firstOrFail();

    expect($balance->used)->toBe(1.0)
        ->and($balance->pending)->toBe(0.0)
        ->and($balance->balance)->toBe(9.0)
        ->and(LeaveBalanceTransaction::query()
            ->where('leave_request_id', $leaveRequest->id)
            ->pluck('transaction_type')
            ->all())->toBe(['used_increased']);
});

test('admin balance creation and edits record movements and preserve balance identity', function () {
    $admin = createHrmsLoginUser('admin');
    $employee = createLeaveBalanceLedgerEmployee();
    $leaveType = createLeaveBalanceLedgerType();

    $this->actingAs($admin)
        ->post(route('leave-balances.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated' => 12,
            'carried_forward' => 2,
            'used' => 3,
            'pending' => 1,
            'encashed' => 1,
            'earned' => 1,
            'remarks' => 'Opening balance imported by HR.',
        ])
        ->assertRedirect(route('leave-balances.index'));

    $balance = LeaveBalance::query()->firstOrFail();

    expect($balance->balance)->toBe(10.0);

    $this->actingAs($admin)
        ->put(route('leave-balances.update', $balance), [
            'employee_id' => $employee->id + 1,
            'leave_type_id' => $leaveType->id + 1,
            'year' => 2030,
            'allocated' => 14,
            'carried_forward' => 2,
            'used' => 3,
            'pending' => 1,
            'encashed' => 1,
            'earned' => 1,
            'remarks' => 'Annual allocation adjustment.',
        ])
        ->assertRedirect(route('leave-balances.index'));

    expect($balance->fresh()->employee_id)->toBe($employee->id)
        ->and($balance->fresh()->leave_type_id)->toBe($leaveType->id)
        ->and($balance->fresh()->year)->toBe(2026)
        ->and($balance->fresh()->balance)->toBe(12.0)
        ->and($balance->transactions()->count())->toBe(7)
        ->and($balance->transactions()->where('transaction_type', 'allocated_increased')->count())->toBe(2);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-balances.transactions', $balance))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-balances/transactions')
            ->where('leaveBalance.id', $balance->id)
            ->has('transactions.data', 7)
        );

    $this->actingAs($admin)
        ->delete(route('leave-balances.destroy', $balance))
        ->assertSessionHasErrors('balance');

    expect($balance->fresh())->not->toBeNull();
});
