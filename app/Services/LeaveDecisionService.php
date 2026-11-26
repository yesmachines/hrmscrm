<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\SalesCrm\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveDecisionService
{
    public function __construct(
        private readonly LeaveValidationService $validationService,
        private readonly LeaveRequestBalanceService $balanceService,
        private readonly WorkFromHomeService $workFromHomeService,
        private readonly LeavePdfGenerationService $pdfService,
    ) {}

    /**
     * @param  Builder<LeaveRequest>  $requests
     * @param  'approved'|'rejected'  $status
     */
    public function decide(
        Builder $requests,
        int $id,
        string $status,
        int $actorId,
        ?string $remarks,
    ): ?LeaveRequest {
        $leave = DB::transaction(function () use ($requests, $id, $status, $actorId, $remarks): ?LeaveRequest {
            $leave = $requests->whereKey($id)->lockForUpdate()->first();
            if ($leave === null) {
                return null;
            }

            $type = $leave->leaveType;
            $isWfh = $this->workFromHomeService->isWorkFromHomeType($type);
            if ($leave->status !== 'applied') {
                $subject = $isWfh ? 'WFH' : 'leave';

                throw ValidationException::withMessages([
                    'status' => ["Only applied {$subject} requests can be {$status}."],
                ]);
            }

            $validation = $status === 'approved'
                ? $this->validationService->validateApplication(
                    Employee::query()->findOrFail($leave->employee_id),
                    $this->validationService->dataForUpdate($leave, []),
                    $leave->files()->exists(),
                    (int) $leave->id,
                    true,
                    false,
                )
                : null;

            if ($isWfh) {
                $oldScheduleId = $this->workFromHomeService->scheduleIdForRequest($leave);
                $schedule = $validation['wfh_schedule'] ?? null;
                if ($schedule !== null) {
                    $this->workFromHomeService->linkRequestToSchedule($leave, $schedule);
                }

                $this->workFromHomeService->applyRequestBalanceTransition(
                    $leave,
                    'applied',
                    $leave->total_days,
                    $oldScheduleId,
                    $status,
                    $leave->total_days,
                    $schedule?->id,
                );
            } else {
                $this->balanceService->releasePending($leave);
                if ($validation !== null) {
                    $this->balanceService->deductApproved($leave, $type, $validation['policy']);
                }
            }

            $leave->update(['status' => $status]);
            $leave->histories()->create([
                'action_type' => $status,
                'remarks' => $remarks,
                'done_by' => $actorId,
                'action_on' => now(),
            ]);
            if ($isWfh || $type->requires_approval) {
                $leave->approvals()->create([
                    'approval_level' => 'Final',
                    'approver_id' => $actorId,
                    'remarks' => $remarks,
                    'approved_date' => now(),
                ]);
            }

            return $leave;
        });

        if ($leave !== null && $status === 'approved') {
            $this->pdfService->generateIfAvailable($leave, $actorId);
        }

        return $leave;
    }
}
