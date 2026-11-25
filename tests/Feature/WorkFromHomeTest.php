<?php

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Models\WorkFromHomeSchedule;
use App\Services\WorkFromHomeService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{SalesCrmUser, Employee}
 */
function createWorkFromHomeEmployee(int $departmentId): array
{
    $user = SalesCrmUser::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => fake()->unique()->bothify('WFH-#####'),
        'employee_code' => fake()->unique()->bothify('WFH-EMP-#####'),
        'designation' => 'Engineer',
        'division' => 'Technology',
        'department_id' => $departmentId,
        'status' => 1,
        'has_report' => false,
    ]);

    return [$user, $employee];
}

function createWorkFromHomeLeaveType(): LeaveType
{
    return LeaveType::query()->updateOrCreate(
        ['code' => WorkFromHomeService::LEAVE_TYPE_CODE],
        [
            'leave_name' => 'Work From Home',
            'is_paid' => true,
            'requires_approval' => true,
            'gender' => null,
            'religion' => null,
            'status' => 1,
        ],
    );
}

function createWorkFromHomeSchedule(
    Employee $employee,
    int $workingDays,
    Carbon $effectiveFrom,
    Carbon $effectiveTo,
    int $createdBy,
): WorkFromHomeSchedule {
    return app(WorkFromHomeService::class)->createSchedule([
        'employee_id' => (int) $employee->id,
        'working_days' => $workingDays,
        'requested_date' => now()->toDateString(),
        'effective_from' => $effectiveFrom->toDateString(),
        'effective_to' => $effectiveTo->toDateString(),
    ], $createdBy);
}

function nextWorkFromHomeWeekday(CarbonInterface $date): Carbon
{
    $date = Carbon::instance($date);

    while (! $date->isWeekday()) {
        $date->addDay();
    }

    return $date;
}

/**
 * @return array{string, string}
 */
function workFromHomeDateRange(Carbon $startDate, int $workingDays): array
{
    $endDate = $startDate->copy();
    $countedDays = 0;

    while ($countedDays < $workingDays) {
        if ($endDate->isWeekday()) {
            $countedDays++;
        }

        if ($countedDays < $workingDays) {
            $endDate->addDay();
        }
    }

    return [$startDate->toDateString(), $endDate->toDateString()];
}

test('hr can create a WFH schedule and allocate its working days', function () {
    $hrUser = createHrmsLoginUser('hr');
    [, $employee] = createWorkFromHomeEmployee(101);
    createWorkFromHomeLeaveType();

    $effectiveFrom = nextWorkFromHomeWeekday(now()->startOfDay()->addDays(4));
    $effectiveTo = $effectiveFrom->copy()->addDays(20);

    $hrToken = $hrUser->createToken('test')->plainTextToken;
    $this->withToken($hrToken)
        ->postJson(route('api.v1.hr.wfh.schedules.store'), [
            'employee_id' => $employee->id,
            'working_days' => 3,
            'requested_date' => now()->toDateString(),
            'effective_from' => $effectiveFrom->toDateString(),
            'effective_to' => $effectiveTo->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('schedule.working_days', 3)
        ->assertJsonPath('schedule.status', 1);

    $schedule = WorkFromHomeSchedule::query()->where('employee_id', $employee->id)->firstOrFail();
    $balance = app(WorkFromHomeService::class)->balanceForSchedule($schedule);

    expect((float) $balance->allocated)->toBe(3.0)
        ->and((float) $balance->balance)->toBe(3.0);

    $this->withToken($hrToken)
        ->postJson(route('api.v1.hr.wfh.schedules.store'), [
            'employee_id' => $employee->id,
            'working_days' => 1,
            'requested_date' => now()->toDateString(),
            'effective_from' => $effectiveFrom->copy()->addDays(5)->toDateString(),
            'effective_to' => $effectiveTo->copy()->addDays(5)->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['effective_from']);

    $financeUser = createHrmsLoginUser('finance');
    Auth::forgetGuards();
    $this->withToken($financeUser->createToken('test')->plainTextToken)
        ->getJson(route('api.v1.hr.wfh.schedules.index'))
        ->assertForbidden();

    $leaveType = LeaveType::query()->where('code', WorkFromHomeService::LEAVE_TYPE_CODE)->firstOrFail();
    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $effectiveFrom->toDateString(),
        'end_date' => $effectiveFrom->toDateString(),
        'total_days' => 1,
        'status' => 'applied',
    ]);

    $this->actingAs($financeUser)
        ->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-requests/index')
            ->where('requests.data', fn ($requests) => collect($requests)->doesntContain(
                fn ($request) => (int) $request['id'] === (int) $leaveRequest->id,
            ))
        );

    $this->get(route('leave-requests.show', $leaveRequest))
        ->assertForbidden();

    $this->get(route('wfh-schedules.index'))
        ->assertForbidden();
});

test('employee WFH requests count actual days against the schedule allowance', function () {
    [$user, $employee] = createWorkFromHomeEmployee(102);
    $leaveType = createWorkFromHomeLeaveType();
    $startDate = nextWorkFromHomeWeekday(now()->startOfDay()->addDays(4));
    $schedule = createWorkFromHomeSchedule(
        $employee,
        3,
        $startDate,
        $startDate->copy()->addDays(30),
        (int) $user->id,
    );
    $token = $user->createToken('test')->plainTextToken;
    [$firstStart, $firstEnd] = workFromHomeDateRange($startDate, 1);

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $firstStart,
        'end_date' => $firstEnd,
        'request_type' => 'fullday',
        'remarks' => 'Home internet repair.',
    ])->assertCreated();

    $leaveRequest = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();
    expect((float) $leaveRequest->total_days)->toBe(1.0)
        ->and($leaveRequest->details()->where('field_key', 'wfh_schedule_id')->value('field_value'))
        ->toBe((string) $schedule->id);

    $nextStart = nextWorkFromHomeWeekday($startDate->copy()->addDay());
    [$secondStart, $secondEnd] = workFromHomeDateRange($nextStart, 3);

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $secondStart,
        'end_date' => $secondEnd,
        'request_type' => 'fullday',
        'remarks' => 'Three days at home.',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['total_days']);

    $balance = app(WorkFromHomeService::class)->balanceForSchedule($schedule);
    expect((float) $balance->pending)->toBe(1.0)
        ->and((float) $balance->used)->toBe(0.0)
        ->and((float) $balance->balance)->toBe(3.0)
        ->and(LeaveRequest::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

test('approval and schedule status changes update WFH balances by requested days', function () {
    $hrUser = createHrmsLoginUser('hr');
    [$user, $employee] = createWorkFromHomeEmployee(103);
    $leaveType = createWorkFromHomeLeaveType();
    $startDate = nextWorkFromHomeWeekday(now()->startOfDay()->addDays(4));
    $schedule = createWorkFromHomeSchedule(
        $employee,
        3,
        $startDate,
        $startDate->copy()->addDays(30),
        (int) $hrUser->id,
    );
    $token = $user->createToken('test')->plainTextToken;
    [$firstStart, $firstEnd] = workFromHomeDateRange($startDate, 2);

    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $firstStart,
        'end_date' => $firstEnd,
        'request_type' => 'fullday',
        'remarks' => 'Two days at home.',
    ])->assertCreated();

    $firstRequest = LeaveRequest::query()->where('employee_id', $employee->id)->firstOrFail();
    $secondStart = nextWorkFromHomeWeekday(Carbon::parse($firstEnd)->addDay());
    [$secondStart, $secondEnd] = workFromHomeDateRange($secondStart, 1);
    $this->withToken($token)->postJson('/api/v1/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $secondStart,
        'end_date' => $secondEnd,
        'request_type' => 'fullday',
        'remarks' => 'One more day at home.',
    ])->assertCreated();

    $secondRequest = LeaveRequest::query()
        ->where('employee_id', $employee->id)
        ->where('id', '!=', $firstRequest->id)
        ->firstOrFail();

    $this->actingAs($hrUser)
        ->from(route('leave-requests.index'))
        ->put(route('leave-requests.update', $firstRequest), [
            'status' => 'approved',
            'remarks' => 'Approved.',
        ])
        ->assertRedirect(route('leave-requests.index'));

    $balance = app(WorkFromHomeService::class)->balanceForSchedule($schedule);
    expect((float) $balance->pending)->toBe(1.0)
        ->and((float) $balance->used)->toBe(2.0)
        ->and((float) $balance->balance)->toBe(1.0);

    app(WorkFromHomeService::class)->updateScheduleStatus($schedule, 0);
    $balance->refresh();
    expect((float) $balance->allocated)->toBe(2.0)
        ->and((float) $balance->used)->toBe(2.0)
        ->and((float) $balance->balance)->toBe(0.0)
        ->and((float) $balance->pending)->toBe(1.0);

    $this->actingAs($hrUser)
        ->from(route('leave-requests.index'))
        ->put(route('leave-requests.update', $secondRequest), [
            'status' => 'approved',
            'remarks' => 'Cannot approve inactive schedule.',
        ])
        ->assertSessionHasErrors(['start_date']);

    expect($secondRequest->fresh()->status)->toBe('applied');

    app(WorkFromHomeService::class)->updateScheduleStatus($schedule, 1);
    $balance->refresh();
    expect((float) $balance->allocated)->toBe(3.0)
        ->and((float) $balance->balance)->toBe(1.0);

    $this->actingAs($hrUser)
        ->from(route('leave-requests.index'))
        ->put(route('leave-requests.update', $secondRequest), [
            'status' => 'approved',
            'remarks' => 'Approved after reactivation.',
        ])
        ->assertRedirect(route('leave-requests.index'));

    $balance->refresh();
    expect((float) $balance->pending)->toBe(0.0)
        ->and((float) $balance->used)->toBe(3.0)
        ->and((float) $balance->balance)->toBe(0.0);
});

test('WFH requests enforce notice period, schedule dates, and department conflicts', function () {
    $this->travelTo(Carbon::parse('2026-01-05 10:00:00'));
    [$firstUser, $firstEmployee] = createWorkFromHomeEmployee(104);
    [$secondUser, $secondEmployee] = createWorkFromHomeEmployee(104);
    $leaveType = createWorkFromHomeLeaveType();
    $effectiveFrom = Carbon::parse('2026-01-05');
    $effectiveTo = Carbon::parse('2026-01-31');
    createWorkFromHomeSchedule($firstEmployee, 2, $effectiveFrom, $effectiveTo, (int) $firstUser->id);
    createWorkFromHomeSchedule($secondEmployee, 2, $effectiveFrom, $effectiveTo, (int) $secondUser->id);

    $this->withToken($firstUser->createToken('test')->plainTextToken)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-01-06',
            'end_date' => '2026-01-06',
            'request_type' => 'fullday',
            'remarks' => 'Short notice.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);

    $this->withToken($firstUser->createToken('test')->plainTextToken)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-02',
            'request_type' => 'fullday',
            'remarks' => 'Outside schedule.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);

    $this->withToken($firstUser->createToken('test')->plainTextToken)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-01-07',
            'end_date' => '2026-01-07',
            'request_type' => 'fullday',
            'remarks' => 'Within schedule.',
        ])
        ->assertCreated();

    $this->withToken($secondUser->createToken('test')->plainTextToken)
        ->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-01-07',
            'end_date' => '2026-01-07',
            'request_type' => 'fullday',
            'remarks' => 'Same department.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);

    expect(LeaveRequest::query()->where('leave_type_id', $leaveType->id)->count())->toBe(1);
});

test('hr can list, view, approve, and reject WFH requests through the API', function () {
    $hrUser = createHrmsLoginUser('hr');
    [$user, $employee] = createWorkFromHomeEmployee(105);
    $leaveType = createWorkFromHomeLeaveType();
    $startDate = nextWorkFromHomeWeekday(now()->startOfDay()->addDays(4));
    $schedule = createWorkFromHomeSchedule(
        $employee,
        3,
        $startDate,
        $startDate->copy()->addDays(30),
        (int) $hrUser->id,
    );
    $token = $user->createToken('test')->plainTextToken;
    [$firstStart, $firstEnd] = workFromHomeDateRange($startDate, 1);
    $nextStart = nextWorkFromHomeWeekday($startDate->copy()->addDay());
    [$secondStart, $secondEnd] = workFromHomeDateRange($nextStart, 1);

    foreach ([[$firstStart, $firstEnd], [$secondStart, $secondEnd]] as [$start, $end]) {
        $this->withToken($token)->postJson('/api/v1/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'request_type' => 'fullday',
            'remarks' => 'Working from home.',
        ])->assertCreated();
    }

    $requests = LeaveRequest::query()->where('employee_id', $employee->id)->orderBy('id')->get();
    $hrToken = $hrUser->createToken('test')->plainTextToken;
    Auth::forgetGuards();

    $this->withToken($hrToken)
        ->getJson(route('api.v1.hr.wfh.requests.index', ['status' => 'applied']))
        ->assertOk()
        ->assertJsonPath('requests.total', 2)
        ->assertJsonPath('requests.data.0.wfh_schedule_id', $schedule->id);

    $this->withToken($hrToken)
        ->getJson(route('api.v1.hr.wfh.requests.show', $requests[0]->id))
        ->assertOk()
        ->assertJsonPath('request.status', 'applied')
        ->assertJsonCount(1, 'histories');

    $this->withToken($hrToken)
        ->postJson(route('api.v1.hr.wfh.requests.approve', $requests[0]->id), ['remarks' => 'Approved.'])
        ->assertOk()
        ->assertJsonPath('request.status', 'approved');

    $this->withToken($hrToken)
        ->postJson(route('api.v1.hr.wfh.requests.reject', $requests[1]->id), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['remarks']);

    $this->withToken($hrToken)
        ->postJson(route('api.v1.hr.wfh.requests.reject', $requests[1]->id), ['remarks' => 'No coverage.'])
        ->assertOk()
        ->assertJsonPath('request.status', 'rejected');

    $this->withToken($hrToken)
        ->postJson(route('api.v1.hr.wfh.requests.approve', $requests[1]->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $balance = app(WorkFromHomeService::class)->balanceForSchedule($schedule);
    expect((float) $balance->pending)->toBe(0.0)
        ->and((float) $balance->used)->toBe(1.0)
        ->and((float) $balance->balance)->toBe(2.0);

    $this->withToken($hrToken)
        ->getJson(route('api.v1.hr.wfh.requests.show', 999999))
        ->assertNotFound();

    Auth::forgetGuards();
    $this->withToken($token)
        ->getJson(route('api.v1.hr.wfh.requests.index'))
        ->assertForbidden();
});
