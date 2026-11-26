<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\WorkFromHomeSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class WorkFromHomeService
{
    public const LEAVE_TYPE_CODE = 'WFH';

    public const SCHEDULE_DETAIL_KEY = 'wfh_schedule_id';

    public function __construct(private readonly LeaveBalanceService $leaveBalanceService) {}

    public function isWorkFromHomeType(LeaveType $leaveType): bool
    {
        return $leaveType->code === self::LEAVE_TYPE_CODE;
    }

    /**
     * @param  array{employee_id: int, working_days: int, requested_date: string, effective_from?: string|null, effective_to?: string|null}  $data
     */
    public function createSchedule(array $data, int $createdBy): WorkFromHomeSchedule
    {
        return DB::transaction(function () use ($data, $createdBy): WorkFromHomeSchedule {
            $effectiveFrom = $data['effective_from'] ?? null;
            $effectiveTo = $data['effective_to'] ?? null;
            $this->assertNoActiveScheduleOverlap(
                (int) $data['employee_id'],
                $effectiveFrom !== null ? Carbon::parse($effectiveFrom) : null,
                $effectiveTo !== null ? Carbon::parse($effectiveTo) : null,
            );

            $schedule = WorkFromHomeSchedule::query()->create([
                'employee_id' => (int) $data['employee_id'],
                'working_days' => (int) $data['working_days'],
                'requested_date' => $data['requested_date'],
                'status' => 1,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'created_by' => $createdBy,
            ]);

            $this->adjustBalance(
                $schedule,
                'allocated',
                (float) $schedule->working_days,
                $createdBy,
                null,
                "WFH schedule #{$schedule->id} created.",
                true,
            );

            return $schedule;
        });
    }

    public function updateScheduleStatus(
        WorkFromHomeSchedule $schedule,
        int $status,
        ?int $actorId = null,
    ): WorkFromHomeSchedule {
        if (! in_array($status, [0, 1], true)) {
            throw ValidationException::withMessages([
                'status' => ['The selected WFH schedule status is invalid.'],
            ]);
        }

        return DB::transaction(function () use ($schedule, $status, $actorId): WorkFromHomeSchedule {
            $schedule = WorkFromHomeSchedule::query()
                ->whereKey($schedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($schedule->status === $status) {
                return $schedule;
            }

            if ($status === 1) {
                $this->assertNoActiveScheduleOverlap(
                    (int) $schedule->employee_id,
                    $schedule->effective_from,
                    $schedule->effective_to,
                    (int) $schedule->id,
                );
            }

            $usedDays = $this->requestDaysForSchedule($schedule, ['approved']);
            if ($usedDays > (float) $schedule->working_days) {
                throw new LogicException('Approved WFH days exceed the schedule allowance.');
            }

            $unusedDays = max(0, (float) $schedule->working_days - $usedDays);
            $balanceAdjustment = $status === 0 ? -$unusedDays : $unusedDays;

            $this->adjustBalance(
                $schedule,
                'allocated',
                $balanceAdjustment,
                $actorId,
                null,
                "WFH schedule #{$schedule->id} was ".($status === 1 ? 'reactivated.' : 'deactivated.'),
                $balanceAdjustment > 0,
            );

            $schedule->update(['status' => $status]);

            return $schedule->refresh();
        });
    }

    public function validateRequest(
        Employee $employee,
        Carbon $startDate,
        Carbon $endDate,
        float $totalDays,
        ?int $ignoreLeaveRequestId = null,
        bool $enforceNoticePeriod = true,
    ): WorkFromHomeSchedule {
        $schedule = $this->scheduleForRequestedDates($employee, $startDate, $endDate);

        if ($enforceNoticePeriod && $startDate->copy()->startOfDay()->lt(now()->addHours(24))) {
            throw ValidationException::withMessages([
                'start_date' => ['WFH requests must be submitted at least 24 hours before the first requested date.'],
            ]);
        }

        $activeDays = $this->requestDaysForSchedule(
            $schedule,
            config('params.leave_request.active_statuses'),
            $ignoreLeaveRequestId,
        );
        if ($activeDays + $totalDays > (float) $schedule->working_days) {
            throw ValidationException::withMessages([
                'total_days' => ['The request exceeds the remaining WFH working-day allowance.'],
            ]);
        }

        $pendingDays = $this->requestDaysForSchedule(
            $schedule,
            ['applied'],
            $ignoreLeaveRequestId,
        );
        $balance = $this->balanceForSchedule($schedule);
        $availableBalance = (float) ($balance->balance ?? 0);
        if ($ignoreLeaveRequestId !== null) {
            $approvedDays = LeaveRequest::query()
                ->whereKey($ignoreLeaveRequestId)
                ->where('status', 'approved')
                ->whereHas('details', fn ($query) => $query
                    ->where('field_key', self::SCHEDULE_DETAIL_KEY)
                    ->where('field_value', (string) $schedule->id))
                ->value('total_days');
            $availableBalance += (float) ($approvedDays ?? 0);
        }

        if ($availableBalance < $pendingDays + $totalDays) {
            throw ValidationException::withMessages([
                'total_days' => ['The WFH balance is not enough for this request.'],
            ]);
        }

        $this->validateDepartmentOverlap($employee, $startDate, $endDate, $ignoreLeaveRequestId);

        return $schedule;
    }

    public function linkRequestToSchedule(LeaveRequest $leaveRequest, WorkFromHomeSchedule $schedule): void
    {
        $leaveRequest->details()->updateOrCreate(
            ['field_key' => self::SCHEDULE_DETAIL_KEY],
            [
                'field_name' => 'WFH Schedule ID',
                'field_value' => (string) $schedule->id,
            ],
        );
    }

    public function scheduleIdForRequest(LeaveRequest $leaveRequest): ?int
    {
        $value = $leaveRequest->details()
            ->where('field_key', self::SCHEDULE_DETAIL_KEY)
            ->value('field_value');

        return is_numeric($value) ? (int) $value : null;
    }

    public function applyRequestBalanceTransition(
        LeaveRequest $leaveRequest,
        string $oldStatus,
        float $oldDays,
        ?int $oldScheduleId,
        string $newStatus,
        float $newDays,
        ?int $newScheduleId,
        ?int $actorId = null,
    ): void {
        DB::transaction(function () use (
            $leaveRequest,
            $oldStatus,
            $oldDays,
            $oldScheduleId,
            $newStatus,
            $newDays,
            $newScheduleId,
            $actorId,
        ): void {
            $lockedRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($oldStatus !== '' && $lockedRequest->status !== $oldStatus) {
                throw ValidationException::withMessages([
                    'status' => ['This leave request was already updated. Refresh the page and try again.'],
                ]);
            }

            $lockedRequest->loadMissing('leaveType');
            if ($lockedRequest->leaveType === null || ! $this->isWorkFromHomeType($lockedRequest->leaveType)) {
                return;
            }

            if (in_array($oldStatus, ['applied', 'approved'], true) && $oldScheduleId === null) {
                $oldScheduleId = $this->scheduleIdForRequest($lockedRequest);
            }

            $oldSchedule = $oldScheduleId === null
                ? null
                : WorkFromHomeSchedule::query()->whereKey($oldScheduleId)->lockForUpdate()->first();

            if (in_array($oldStatus, ['applied', 'approved'], true) && $oldSchedule === null) {
                throw ValidationException::withMessages([
                    'status' => ['The WFH schedule for this request could not be found.'],
                ]);
            }
            if ($oldSchedule !== null && (int) $oldSchedule->employee_id !== (int) $lockedRequest->employee_id) {
                throw ValidationException::withMessages([
                    'status' => ['The WFH schedule does not belong to this employee.'],
                ]);
            }

            $newSchedule = in_array($newStatus, ['applied', 'approved'], true)
                ? WorkFromHomeSchedule::query()->whereKey($newScheduleId)->lockForUpdate()->first()
                : null;
            if (in_array($newStatus, ['applied', 'approved'], true) && $newSchedule === null) {
                throw ValidationException::withMessages([
                    'status' => ['An active WFH schedule is required for this request.'],
                ]);
            }
            if ($newSchedule !== null && (int) $newSchedule->employee_id !== (int) $lockedRequest->employee_id) {
                throw ValidationException::withMessages([
                    'status' => ['The WFH schedule does not belong to this employee.'],
                ]);
            }
            if ($newStatus === 'approved' && ($newSchedule === null || $newSchedule->status !== 1)) {
                throw ValidationException::withMessages([
                    'status' => ['A request on an inactive WFH schedule cannot be approved.'],
                ]);
            }

            $remarks = "WFH leave request #{$lockedRequest->id}: {$oldStatus} to {$newStatus}.";
            if ($oldStatus === 'applied') {
                $this->adjustBalance($oldSchedule, 'pending', -$oldDays, $actorId, $lockedRequest, $remarks);
            } elseif ($oldStatus === 'approved') {
                $this->adjustBalance($oldSchedule, 'used', -$oldDays, $actorId, $lockedRequest, $remarks);
                if ($oldSchedule->status !== 1) {
                    $this->adjustBalance($oldSchedule, 'allocated', -$oldDays, $actorId, $lockedRequest, $remarks);
                }
            }

            if ($newStatus === 'applied') {
                $this->adjustBalance($newSchedule, 'pending', $newDays, $actorId, $lockedRequest, $remarks);
            } elseif ($newStatus === 'approved') {
                $this->adjustBalance($newSchedule, 'used', $newDays, $actorId, $lockedRequest, $remarks);
            }
        });
    }

    public function balanceYear(WorkFromHomeSchedule $schedule): int
    {
        return $schedule->effective_from?->year
            ?? $schedule->created_at?->year
            ?? (int) now()->format('Y');
    }

    private function scheduleForRequestedDates(
        Employee $employee,
        Carbon $startDate,
        Carbon $endDate,
    ): WorkFromHomeSchedule {
        $matchingSchedules = WorkFromHomeSchedule::query()
            ->where('employee_id', $employee->id)
            ->where('status', 1)
            ->get()
            ->filter(function (WorkFromHomeSchedule $schedule) use ($startDate, $endDate): bool {
                return ($schedule->effective_from === null || $startDate->gte($schedule->effective_from->copy()->startOfDay()))
                    && ($schedule->effective_to === null || $endDate->lte($schedule->effective_to->copy()->endOfDay()));
            });

        if ($matchingSchedules->count() !== 1) {
            throw ValidationException::withMessages([
                'start_date' => [
                    $matchingSchedules->isEmpty()
                        ? 'No active WFH schedule covers the requested dates.'
                        : 'More than one active WFH schedule covers the requested dates.',
                ],
            ]);
        }

        return $matchingSchedules->first();
    }

    private function assertNoActiveScheduleOverlap(
        int $employeeId,
        ?CarbonInterface $effectiveFrom,
        ?CarbonInterface $effectiveTo,
        ?int $ignoreScheduleId = null,
    ): void {
        $existingSchedules = WorkFromHomeSchedule::query()
            ->where('employee_id', $employeeId)
            ->where('status', 1)
            ->when($ignoreScheduleId !== null, fn ($query) => $query->where('id', '!=', $ignoreScheduleId))
            ->lockForUpdate()
            ->get(['id', 'effective_from', 'effective_to']);

        foreach ($existingSchedules as $schedule) {
            $startsBeforeExistingEnds = $effectiveTo === null
                || $schedule->effective_from === null
                || $schedule->effective_from->lte($effectiveTo);
            $endsAfterExistingStarts = $effectiveFrom === null
                || $schedule->effective_to === null
                || $schedule->effective_to->gte($effectiveFrom);

            if ($startsBeforeExistingEnds && $endsAfterExistingStarts) {
                throw ValidationException::withMessages([
                    'effective_from' => ['This employee already has an active WFH schedule covering these dates.'],
                ]);
            }
        }
    }

    private function requestDaysForSchedule(
        WorkFromHomeSchedule $schedule,
        array $statuses,
        ?int $ignoreLeaveRequestId = null,
    ): float {
        $leaveType = $this->workFromHomeLeaveType();

        return (float) LeaveRequest::query()
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('status', $statuses)
            ->whereHas('details', fn ($query) => $query
                ->where('field_key', self::SCHEDULE_DETAIL_KEY)
                ->where('field_value', (string) $schedule->id))
            ->when($ignoreLeaveRequestId !== null, fn ($query) => $query->where('id', '!=', $ignoreLeaveRequestId))
            ->sum('total_days');
    }

    private function validateDepartmentOverlap(
        Employee $employee,
        Carbon $startDate,
        Carbon $endDate,
        ?int $ignoreLeaveRequestId,
    ): void {
        if ($employee->department_id === null) {
            throw ValidationException::withMessages([
                'employee_id' => ['A department is required to verify WFH eligibility. Please ask HR to assign the employee department.'],
            ]);
        }

        $departmentEmployeeIds = Employee::query()
            ->where('organisation_id', $employee->organisation_id)
            ->where('department_id', $employee->department_id)
            ->where('id', '!=', $employee->id)
            ->pluck('id');

        if ($departmentEmployeeIds->isEmpty()) {
            return;
        }

        if (LeaveRequest::query()
            ->where('leave_type_id', $this->workFromHomeLeaveType()->id)
            ->whereIn('employee_id', $departmentEmployeeIds)
            ->whereIn('status', config('params.leave_request.active_statuses'))
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->when($ignoreLeaveRequestId !== null, fn ($query) => $query->where('id', '!=', $ignoreLeaveRequestId))
            ->exists()) {
            throw ValidationException::withMessages([
                'start_date' => ['Another employee in this department has an active WFH request during the selected dates.'],
            ]);
        }
    }

    private function adjustBalance(
        WorkFromHomeSchedule $schedule,
        string $field,
        float $amount,
        ?int $actorId = null,
        ?LeaveRequest $leaveRequest = null,
        string $remarks = 'WFH balance adjustment.',
        bool $createIfMissing = false,
    ): void {
        if (abs($amount) < 0.00001) {
            return;
        }

        $leaveType = $this->workFromHomeLeaveType();
        $this->leaveBalanceService->adjustComponent(
            (int) $schedule->employee_id,
            (int) $leaveType->id,
            $this->balanceYear($schedule),
            $field,
            $amount,
            $actorId,
            $leaveRequest?->id,
            $remarks,
            $createIfMissing,
        );
    }

    public function balanceForSchedule(WorkFromHomeSchedule $schedule): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->where('employee_id', $schedule->employee_id)
            ->where('leave_type_id', $this->workFromHomeLeaveType()->id)
            ->where('year', $this->balanceYear($schedule))
            ->first();
    }

    private function workFromHomeLeaveType(): LeaveType
    {
        return LeaveType::query()
            ->where('code', self::LEAVE_TYPE_CODE)
            ->firstOrFail();
    }
}
