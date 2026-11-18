<?php

namespace App\Concerns;

use App\Models\Festival;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

trait LeaveBusinessRules
{
    /** @param array<string, mixed> $data */
    private function validateUnpaidLeave(LeaveType $leaveType, array $data): void
    {
        if (($this->leaveTypeRules->definition($leaveType)['remarks_required'] ?? false)
            && (! is_string($data['remarks'] ?? null) || trim($data['remarks']) === '')) {
            throw ValidationException::withMessages([
                'remarks' => ['A reason is required for this leave type.'],
            ]);
        }
    }

    public function requiresHandover(LeaveType $leaveType, ?LeavePolicy $policy, float $totalDays): bool
    {
        $threshold = $this->leaveTypeRules->definition($leaveType)['handover_after_days'] ?? null;

        return $threshold !== null
            ? $totalDays > $threshold
            : ($policy?->requiresHandoverForDuration($totalDays) ?? false);
    }

    private function validateDepartmentOverlap(
        Employee $employee,
        LeaveType $leaveType,
        ?LeavePolicy $policy,
        Carbon $startDate,
        Carbon $endDate,
        ?int $ignoreLeaveRequestId,
    ): void {
        if (! ($policy?->preventsDepartmentOverlap() ?? false)) {
            return;
        }
        if ($employee->department_id === null) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['A department is required to verify leave eligibility. Please ask HR to assign your department.'],
            ]);
        }

        $departmentEmployeeIds = Employee::query()
            ->where('organisation_id', $employee->organisation_id)
            ->where('department_id', $employee->department_id)
            ->where('id', '!=', $employee->id)
            ->pluck('id');

        if (LeaveRequest::query()
            ->whereIn('employee_id', $departmentEmployeeIds)
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('status', config('params.leave_request.active_statuses'))
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->when($ignoreLeaveRequestId !== null, fn ($query) => $query->where('id', '!=', $ignoreLeaveRequestId))
            ->exists()) {
            throw ValidationException::withMessages([
                'start_date' => ['Another employee in this department has an active request for the same leave type during the selected dates.'],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateMaternityLeave(array $data): void
    {
        $this->requiredDetail('due_date', $data['due_date'] ?? null);
    }

    public function validateParentalLeave(LeaveType $leaveType, mixed $childBirthDate, Carbon $leaveStartDate): void
    {
        if (($this->leaveTypeRules->definition($leaveType)['validator'] ?? null) !== 'parental') {
            return;
        }
        $value = $this->requiredDetail('child_birth_date', $childBirthDate);
        if ($value === null) {
            return;
        }
        $birthDate = Carbon::parse($value)->startOfDay();
        $months = config('params.leave_type.parental_birth_window_months');
        if (abs($birthDate->diffInMonths($leaveStartDate)) >= $months) {
            throw ValidationException::withMessages([
                'child_birth_date' => ["Parental Leave must start within {$months} months of the child's birth."],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateCompassionateLeave(array $data, float $totalDays): void
    {
        $relationship = $this->requiredDetail('family_relationship', $data['family_relationship'] ?? null);
        if ($relationship === null) {
            return;
        }
        $relationships = config('params.leave_type.compassionate_relationships');
        if (! array_key_exists($relationship, $relationships)) {
            throw ValidationException::withMessages([
                'family_relationship' => ['Please select a valid family relationship for Compassionate Leave.'],
            ]);
        }
        $rule = $relationships[$relationship];
        if ($totalDays > $rule['max_days']) {
            throw ValidationException::withMessages([
                'total_days' => ["Compassionate Leave for {$rule['label']} cannot exceed {$rule['max_days']} days per request."],
            ]);
        }
    }

    private function requiresHolidayAttachment(LeaveType $leaveType, Carbon $startDate, Carbon $endDate): bool
    {
        if (! ($this->leaveTypeRules->definition($leaveType)['requires_holiday_document'] ?? false)) {
            return false;
        }

        $firstDate = $startDate->copy()->subDay()->toDateString();
        $lastDate = $endDate->copy()->addDay()->toDateString();

        return Festival::query()
            ->where('type', 'holiday')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $lastDate)
            ->where(function ($query) use ($firstDate) {
                $query->whereDate('end_date', '>=', $firstDate)
                    ->orWhere(function ($query) use ($firstDate) {
                        $query->whereNull('end_date')->whereDate('start_date', '>=', $firstDate);
                    });
            })
            ->exists();
    }
}
