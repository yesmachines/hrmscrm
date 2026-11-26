<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class LeaveBalanceService
{
    private const COMPONENT_MULTIPLIERS = [
        'allocated' => 1,
        'carried_forward' => 1,
        'earned' => 1,
        'pending' => -1,
        'used' => -1,
        'encashed' => -1,
    ];

    public function __construct(
        private readonly LeaveDurationCalculator $durationCalculator,
        private readonly LeaveTypeRules $leaveTypeRules,
    ) {}

    /**
     * @param  array<int, float>  $requestedDaysByYear
     */
    public function assertAvailable(
        Employee $employee,
        LeaveType $leaveType,
        ?LeavePolicy $policy,
        array $requestedDaysByYear,
        ?int $ignoreLeaveRequestId = null,
    ): void {
        if (! $this->leaveTypeRules->allowsBalance($leaveType, $policy)) {
            return;
        }

        $requestedDaysByYear = $this->normalizeDaysByYear($requestedDaysByYear);
        if ($requestedDaysByYear === []) {
            return;
        }

        $balances = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('year', array_keys($requestedDaysByYear))
            ->get()
            ->keyBy('year');

        $existingDaysByYear = $this->existingActiveRequestDaysByYear(
            $employee,
            $leaveType,
            $ignoreLeaveRequestId,
        );

        foreach ($requestedDaysByYear as $year => $requestedDays) {
            /** @var LeaveBalance|null $balance */
            $balance = $balances->get($year);
            if ($balance === null) {
                throw ValidationException::withMessages([
                    'total_days' => [
                        "A leave balance for {$year} is not configured for {$leaveType->leave_name}. Please contact HR.",
                    ],
                ]);
            }

            $available = $this->calculatedBalance($balance) + ($existingDaysByYear[$year] ?? 0.0);
            if ($available + 0.00001 < $requestedDays) {
                throw ValidationException::withMessages([
                    'total_days' => [
                        sprintf(
                            'Insufficient leave balance for %s in %d. Available: %.2f days, requested: %.2f days.',
                            $leaveType->leave_name,
                            $year,
                            $available,
                            $requestedDays,
                        ),
                    ],
                ]);
            }
        }
    }

    /**
     * @param  array<int, float>  $daysByYear
     */
    public function recordNewRequest(
        LeaveRequest $leaveRequest,
        array $daysByYear,
        ?int $actorId,
        ?string $remarks = null,
    ): void {
        DB::transaction(function () use ($leaveRequest, $daysByYear, $actorId, $remarks): void {
            $this->applyRequestTransition(
                $leaveRequest,
                null,
                [],
                (string) $leaveRequest->status,
                $daysByYear,
                $actorId,
                $remarks,
            );
        });
    }

    /**
     * @param  array<int, float>  $newDaysByYear
     */
    public function transitionRequest(
        LeaveRequest $leaveRequest,
        string $newStatus,
        array $newDaysByYear,
        ?int $actorId,
        ?string $remarks = null,
        ?int $newEmployeeId = null,
        ?int $newLeaveTypeId = null,
    ): void {
        DB::transaction(function () use (
            $leaveRequest,
            $newStatus,
            $newDaysByYear,
            $actorId,
            $remarks,
            $newEmployeeId,
            $newLeaveTypeId,
        ): void {
            $lockedRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isRequestStale = $lockedRequest->status !== $leaveRequest->status
                || (int) $lockedRequest->employee_id !== (int) $leaveRequest->employee_id
                || (int) $lockedRequest->leave_type_id !== (int) $leaveRequest->leave_type_id
                || Carbon::parse($lockedRequest->start_date)->toDateString()
                    !== Carbon::parse($leaveRequest->start_date)->toDateString()
                || $lockedRequest->end_date?->toDateString()
                    !== $leaveRequest->end_date?->toDateString()
                || ($lockedRequest->request_type ?? 'fullday') !== ($leaveRequest->request_type ?? 'fullday')
                || abs((float) $lockedRequest->total_days - (float) $leaveRequest->total_days) > 0.00001;

            if ($isRequestStale) {
                throw ValidationException::withMessages([
                    'status' => ['This leave request was already updated. Refresh the page and try again.'],
                ]);
            }

            $oldDaysByYear = $this->durationCalculator->allocateByCalendarYear(
                Carbon::parse($lockedRequest->start_date),
                Carbon::parse($lockedRequest->end_date ?? $lockedRequest->start_date),
                $lockedRequest->request_type ?? 'fullday',
            );

            $this->applyRequestTransition(
                $lockedRequest,
                (string) $lockedRequest->status,
                $oldDaysByYear,
                $newStatus,
                $newDaysByYear,
                $actorId,
                $remarks,
                $newEmployeeId,
                $newLeaveTypeId,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createOpeningBalance(array $attributes, ?int $actorId, string $remarks): LeaveBalance
    {
        return DB::transaction(function () use ($attributes, $actorId, $remarks): LeaveBalance {
            $components = $this->componentValues($attributes);
            $balance = LeaveBalance::withTrashed()
                ->where('employee_id', (int) $attributes['employee_id'])
                ->where('leave_type_id', (int) $attributes['leave_type_id'])
                ->where('year', (int) $attributes['year'])
                ->lockForUpdate()
                ->first();

            if ($balance !== null && ! $balance->trashed()) {
                throw ValidationException::withMessages([
                    'employee_id' => ['A leave balance already exists for this employee, leave type, and year.'],
                ]);
            }

            $deltas = $components;
            if ($balance !== null) {
                $balance->restore();
                foreach ($components as $component => $value) {
                    $deltas[$component] = round($value - (float) $balance->{$component}, 2);
                }
                $balance->balance = $this->calculatedBalance($balance);
                $balance->save();
            } else {
                $balance = LeaveBalance::query()->create([
                    'employee_id' => (int) $attributes['employee_id'],
                    'leave_type_id' => (int) $attributes['leave_type_id'],
                    'year' => (int) $attributes['year'],
                    ...array_fill_keys(array_keys(self::COMPONENT_MULTIPLIERS), 0),
                    'balance' => 0,
                ]);
            }

            return $this->applyComponentDeltas(
                $balance,
                $deltas,
                $actorId,
                null,
                'Opening balance. '.$remarks,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    public function updateBalanceComponents(
        LeaveBalance $leaveBalance,
        array $newValues,
        ?int $actorId,
        string $remarks,
    ): LeaveBalance {
        return DB::transaction(function () use ($leaveBalance, $newValues, $actorId, $remarks): LeaveBalance {
            $lockedBalance = LeaveBalance::query()
                ->whereKey($leaveBalance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newComponents = $this->componentValues($newValues);
            $deltas = [];

            foreach (self::COMPONENT_MULTIPLIERS as $component => $_multiplier) {
                $deltas[$component] = round(
                    $newComponents[$component] - (float) $lockedBalance->{$component},
                    2,
                );
            }

            return $this->applyComponentDeltas(
                $lockedBalance,
                $deltas,
                $actorId,
                null,
                $remarks,
            );
        });
    }

    public function adjustComponent(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        string $component,
        float $delta,
        ?int $actorId,
        ?int $leaveRequestId,
        string $remarks,
        bool $createIfMissing = false,
    ): LeaveBalance {
        if (! array_key_exists($component, self::COMPONENT_MULTIPLIERS)) {
            throw new LogicException("Unsupported leave balance component [{$component}].");
        }

        return DB::transaction(function () use (
            $employeeId,
            $leaveTypeId,
            $year,
            $component,
            $delta,
            $actorId,
            $leaveRequestId,
            $remarks,
            $createIfMissing,
        ): LeaveBalance {
            $balance = LeaveBalance::withTrashed()
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($balance?->trashed()) {
                $balance->restore();
            }

            if ($balance === null) {
                if (! $createIfMissing) {
                    throw ValidationException::withMessages([
                        'total_days' => [
                            "A leave balance for {$year} is not configured. Please contact HR.",
                        ],
                    ]);
                }

                $balance = LeaveBalance::query()->create([
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'year' => $year,
                    ...array_fill_keys(array_keys(self::COMPONENT_MULTIPLIERS), 0),
                    'balance' => 0,
                ]);
            }

            return $this->applyComponentDeltas(
                $balance,
                [$component => round($delta, 2)],
                $actorId,
                $leaveRequestId,
                $remarks,
            );
        });
    }

    /**
     * @param  array<int, float>  $oldDaysByYear
     * @param  array<int, float>  $newDaysByYear
     */
    private function applyRequestTransition(
        LeaveRequest $leaveRequest,
        ?string $oldStatus,
        array $oldDaysByYear,
        string $newStatus,
        array $newDaysByYear,
        ?int $actorId,
        ?string $remarks,
        ?int $newEmployeeId = null,
        ?int $newLeaveTypeId = null,
    ): void {
        $leaveRequest->loadMissing(['employee', 'leaveType']);
        $oldLeaveType = $leaveRequest->leaveType;
        $oldEmployee = $leaveRequest->employee;
        if ($oldLeaveType === null || $oldEmployee === null) {
            throw new LogicException('A leave balance transition requires an existing employee and leave type.');
        }

        $newEmployeeId ??= (int) $leaveRequest->employee_id;
        $newLeaveTypeId ??= (int) $leaveRequest->leave_type_id;
        $newEmployee = $newEmployeeId === (int) $leaveRequest->employee_id
            ? $oldEmployee
            : Employee::query()->find($newEmployeeId);
        $newLeaveType = $newLeaveTypeId === (int) $leaveRequest->leave_type_id
            ? $oldLeaveType
            : LeaveType::query()->find($newLeaveTypeId);
        if ($newEmployee === null || $newLeaveType === null) {
            throw new LogicException('A leave balance transition requires an existing target employee and leave type.');
        }

        $oldDaysByYear = $this->normalizeDaysByYear($oldDaysByYear);
        $newDaysByYear = $this->normalizeDaysByYear($newDaysByYear);
        $oldComponent = $this->componentForStatus($oldStatus);
        $newComponent = $this->componentForStatus($newStatus);
        $oldPolicy = $oldLeaveType->policyForOrganisation($oldEmployee->organisation_id);
        $newPolicy = $newLeaveType->policyForOrganisation($newEmployee->organisation_id);
        $tracksOldBalance = $oldComponent !== null
            && $this->leaveTypeRules->allowsBalance($oldLeaveType, $oldPolicy);
        $tracksNewBalance = $newComponent !== null
            && $this->leaveTypeRules->allowsBalance($newLeaveType, $newPolicy);
        $balanceChanges = [];

        if ($tracksOldBalance && $oldComponent !== null) {
            foreach ($oldDaysByYear as $year => $days) {
                $key = "{$oldEmployee->id}:{$oldLeaveType->id}:{$year}";
                $balanceChanges[$key] ??= [
                    'employee_id' => (int) $oldEmployee->id,
                    'leave_type_id' => (int) $oldLeaveType->id,
                    'leave_type_name' => $oldLeaveType->leave_name,
                    'year' => (int) $year,
                    'deltas' => [],
                    'old_component' => null,
                    'old_days' => 0.0,
                ];
                $balanceChanges[$key]['deltas'][$oldComponent] =
                    ($balanceChanges[$key]['deltas'][$oldComponent] ?? 0.0) - $days;
                $balanceChanges[$key]['old_component'] = $oldComponent;
                $balanceChanges[$key]['old_days'] += $days;
            }
        }

        if ($tracksNewBalance && $newComponent !== null) {
            foreach ($newDaysByYear as $year => $days) {
                $key = "{$newEmployee->id}:{$newLeaveType->id}:{$year}";
                $balanceChanges[$key] ??= [
                    'employee_id' => (int) $newEmployee->id,
                    'leave_type_id' => (int) $newLeaveType->id,
                    'leave_type_name' => $newLeaveType->leave_name,
                    'year' => (int) $year,
                    'deltas' => [],
                    'old_component' => null,
                    'old_days' => 0.0,
                ];
                $balanceChanges[$key]['deltas'][$newComponent] =
                    ($balanceChanges[$key]['deltas'][$newComponent] ?? 0.0) + $days;
            }
        }

        if ($balanceChanges === []) {
            return;
        }

        ksort($balanceChanges);
        foreach ($balanceChanges as $change) {
            $balance = LeaveBalance::query()
                ->where('employee_id', $change['employee_id'])
                ->where('leave_type_id', $change['leave_type_id'])
                ->where('year', $change['year'])
                ->lockForUpdate()
                ->first();
            if ($balance === null) {
                throw ValidationException::withMessages([
                    'total_days' => [
                        "A leave balance for {$change['year']} is not configured for {$change['leave_type_name']}. Please contact HR.",
                    ],
                ]);
            }

            if ($change['old_component'] !== null) {
                if ((float) $balance->{$change['old_component']} + 0.00001 < $change['old_days']) {
                    throw ValidationException::withMessages([
                        'status' => [
                            "The existing {$change['old_component']} balance for {$change['year']} does not cover this leave request. Please reconcile the balance before changing its status.",
                        ],
                    ]);
                }
            }

            $this->applyComponentDeltas(
                $balance,
                $change['deltas'],
                $actorId,
                (int) $leaveRequest->id,
                $remarks ?? "Leave request #{$leaveRequest->id}: {$oldStatus} to {$newStatus}.",
            );
        }
    }

    /**
     * @param  array<string, float|int>  $deltas
     */
    private function applyComponentDeltas(
        LeaveBalance $balance,
        array $deltas,
        ?int $actorId,
        ?int $leaveRequestId,
        string $remarks,
    ): LeaveBalance {
        $filteredDeltas = [];
        foreach ($deltas as $component => $delta) {
            if (! array_key_exists($component, self::COMPONENT_MULTIPLIERS)) {
                throw new LogicException("Unsupported leave balance component [{$component}].");
            }

            $normalizedDelta = round((float) $delta, 2);
            if (abs($normalizedDelta) >= 0.005) {
                $filteredDeltas[$component] = $normalizedDelta;
            }
        }

        if ($filteredDeltas === []) {
            return $balance;
        }

        $before = $this->calculatedBalance($balance);
        $orderedComponents = array_keys($filteredDeltas);
        usort($orderedComponents, function (string $left, string $right) use ($filteredDeltas): int {
            $leftEffect = self::COMPONENT_MULTIPLIERS[$left] * $filteredDeltas[$left];
            $rightEffect = self::COMPONENT_MULTIPLIERS[$right] * $filteredDeltas[$right];
            $leftPriority = $leftEffect > 0 ? 0 : 1;
            $rightPriority = $rightEffect > 0 ? 0 : 1;

            return $leftPriority <=> $rightPriority;
        });

        foreach ($orderedComponents as $component) {
            $delta = $filteredDeltas[$component];
            $updatedComponent = round((float) $balance->{$component} + $delta, 2);
            if ($updatedComponent < -0.00001) {
                throw ValidationException::withMessages([
                    'balance' => [
                        "The {$component} balance cannot be reduced below zero. Please reconcile the leave balance.",
                    ],
                ]);
            }

            $balance->{$component} = max(0.0, $updatedComponent);
            $after = $this->calculatedBalance($balance);
            if ($after < -0.00001) {
                throw ValidationException::withMessages([
                    'total_days' => ['This change would make the available leave balance negative.'],
                ]);
            }

            $balance->balance = max(0.0, $after);
            $balance->save();

            LeaveBalanceTransaction::query()->create([
                'leave_balance_id' => $balance->id,
                'employee_id' => $balance->employee_id,
                'leave_type_id' => $balance->leave_type_id,
                'leave_request_id' => $leaveRequestId,
                'transaction_type' => $component.'_'.($delta > 0 ? 'increased' : 'decreased'),
                'quantity' => abs($delta),
                'balance_before' => $before,
                'balance_after' => max(0.0, $after),
                'remarks' => $remarks,
                'created_by' => $actorId,
                'created_at' => now(),
            ]);

            $before = max(0.0, $after);
        }

        return $balance->refresh();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, float>
     */
    private function componentValues(array $values): array
    {
        $components = [];
        foreach (self::COMPONENT_MULTIPLIERS as $component => $_multiplier) {
            $components[$component] = round((float) ($values[$component] ?? 0), 2);
        }

        return $components;
    }

    /**
     * @param  array<int, float>  $daysByYear
     * @return array<int, float>
     */
    private function normalizeDaysByYear(array $daysByYear): array
    {
        $normalized = [];
        foreach ($daysByYear as $year => $days) {
            $amount = round((float) $days, 2);
            if ($amount > 0) {
                $normalized[(int) $year] = $amount;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array<int, float>
     */
    private function existingActiveRequestDaysByYear(
        Employee $employee,
        LeaveType $leaveType,
        ?int $ignoreLeaveRequestId,
    ): array {
        if ($ignoreLeaveRequestId === null) {
            return [];
        }

        $request = LeaveRequest::query()
            ->whereKey($ignoreLeaveRequestId)
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('status', config('params.leave_request.active_statuses'))
            ->first();

        if ($request === null) {
            return [];
        }

        return $this->durationCalculator->allocateByCalendarYear(
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date ?? $request->start_date),
            $request->request_type ?? 'fullday',
        );
    }

    /**
     * @param  array<int, float>  $daysByYear
     */
    private function componentForStatus(?string $status): ?string
    {
        return match ($status) {
            'applied' => 'pending',
            'approved' => 'used',
            default => null,
        };
    }

    private function calculatedBalance(LeaveBalance $balance): float
    {
        return $balance->calculatedBalance();
    }
}
