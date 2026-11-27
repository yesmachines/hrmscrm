<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Illuminate\Support\Facades\DB;

class LeaveBalanceAdjustmentService
{
    /**
     * Sets leave_validity to now plus the leave type's policy carry_forward_weeks, for
     * whichever leave type has it configured (not limited to any specific leave type).
     * Every call overwrites the previous validity with a single overall expiry for the
     * balance (per-grant validity tracking is a future to-do).
     */
    public function applyLeaveValidity(LeaveBalance $balance, LeaveType $leaveType, ?int $organisationId): void
    {
        $weeks = $leaveType->policyForOrganisation($organisationId)?->carry_forward_weeks;
        if ($weeks !== null && $weeks > 0) {
            $balance->leave_validity = now()->addWeeks($weeks);
        }
    }

    /**
     * Adds a quantity to an employee's leave balance for a year, creating the balance row if
     * it does not exist yet. leave_validity is set automatically when the leave type's policy
     * configures carry_forward_weeks.
     */
    public function addBalance(Employee $employee, LeaveType $leaveType, int $year, float $quantity): LeaveBalance
    {
        return DB::transaction(function () use ($employee, $leaveType, $year, $quantity): LeaveBalance {
            $balance = LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                $balance = new LeaveBalance([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'year' => $year,
                ]);
            }

            $balance->allocated = (float) ($balance->allocated ?? 0) + $quantity;
            $balance->balance = (float) ($balance->balance ?? 0) + $quantity;

            $this->applyLeaveValidity($balance, $leaveType, $employee->organisation_id);

            $balance->save();

            return $balance;
        });
    }
}
