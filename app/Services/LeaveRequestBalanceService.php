<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;

class LeaveRequestBalanceService
{
    public const API_SUBMITTED = 'Leave application submitted via API.';

    public const API_UPDATED = 'Leave request updated via API.';

    public function __construct(private readonly LeaveTypeRules $leaveTypeRules) {}

    public function reservePending(LeaveRequest $leave, LeaveType $type, ?LeavePolicy $policy): void
    {
        $days = 0.0;
        if ($this->leaveTypeRules->allowsBalance($type, $policy) && ($balance = $this->lockBalance($leave))) {
            $days = $leave->total_days;
            $balance->increment('pending', $days);
        }

        $leave->pending_reserved_days = $days;
    }

    public function releasePending(LeaveRequest $leave): float
    {
        if ($leave->status !== 'applied') {
            return 0.0;
        }

        $days = $leave->pending_reserved_days;
        if ($days === 0.0) {
            return 0.0;
        }

        $balance = $this->lockBalance($leave);
        if ($days === null && $balance !== null) {
            // Before per-request tracking, only self-service API applications reserved regular leave days.
            $history = $leave->histories()->latest('id')->first();
            if ($history?->action_type === 'applied'
                && in_array($history->remarks, [self::API_SUBMITTED, self::API_UPDATED], true)
                && $balance->created_at?->lte($history->action_on)
                && $this->leaveTypeRules->allowsBalance(
                    $leave->leaveType,
                    $leave->leaveType->policyForOrganisation($leave->employee?->organisation_id),
                )
                && $balance->pending >= $leave->total_days) {
                $days = $leave->total_days;
            }
        }

        if ($days > 0 && $balance !== null) {
            $balance->pending = max(0.0, $balance->pending - $days);
            $balance->save();
        }

        $leave->pending_reserved_days = 0.0;

        return $days ?? 0.0;
    }

    public function deductApproved(LeaveRequest $leave, LeaveType $type, ?LeavePolicy $policy): void
    {
        if (! $this->leaveTypeRules->allowsBalance($type, $policy) || ! ($balance = $this->lockBalance($leave))) {
            return;
        }

        $balance->used += $leave->total_days;
        $balance->balance = max(0.0, $balance->balance - $leave->total_days);
        $balance->save();
    }

    private function lockBalance(LeaveRequest $leave): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', $leave->start_date->year)
            ->lockForUpdate()
            ->first();
    }
}
