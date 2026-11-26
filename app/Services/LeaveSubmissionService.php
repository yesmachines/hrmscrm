<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\SalesCrm\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LeaveSubmissionService
{
    public function __construct(
        private readonly LeaveValidationService $validationService,
        private readonly LeaveRequestBalanceService $balanceService,
        private readonly WorkFromHomeService $workFromHomeService,
        private readonly LeavePdfGenerationService $pdfService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitOnBehalf(Employee $employee, array $data, int $actorId, string $actorName): LeaveRequest
    {
        $certificate = $data['certificate'] ?? null;
        $leave = DB::transaction(function () use ($employee, $data, $actorId, $actorName, $certificate): LeaveRequest {
            $validation = $this->validationService->validateApplication($employee, $data, $certificate instanceof UploadedFile);
            $type = $validation['leave_type'];
            $requiresApproval = (bool) $type->requires_approval;
            $status = $requiresApproval ? $data['status'] : 'approved';

            $leave = LeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $validation['start_date']->toDateString(),
                'end_date' => $validation['end_date']->toDateString(),
                'request_type' => $validation['request_type'],
                'total_days' => $validation['total_days'],
                'remarks' => $data['remarks'] ?? null,
                'status' => $status,
                'created_by' => $actorId,
            ]);

            $history = "Applied by HR ({$actorName}) on behalf of employee.";
            if (! $requiresApproval) {
                $history = 'Automatically approved because this leave type does not require approval. '.$history;
            }
            if (! empty($data['remarks'])) {
                $history .= ' Reason: '.$data['remarks'];
            }

            $leave->histories()->create([
                'action_type' => $status,
                'remarks' => $history,
                'done_by' => $actorId,
                'action_on' => now(),
            ]);

            $this->syncHandoverDetails($leave, $data);
            $this->validationService->syncLeaveTypeDetails($leave, $type, $data);
            $this->storeSupportingDetails($leave, $data, $actorId);

            $schedule = $validation['wfh_schedule'];
            if ($schedule !== null) {
                $this->workFromHomeService->linkRequestToSchedule($leave, $schedule);
                $this->workFromHomeService->applyRequestBalanceTransition(
                    $leave, '', 0, null, $status, $leave->total_days, (int) $schedule->id,
                );
            } elseif ($status === 'approved') {
                $this->balanceService->deductApproved($leave, $type, $validation['policy']);
            }

            if ($status === 'approved' && $requiresApproval) {
                $leave->approvals()->create([
                    'approval_level' => 'HR Direct Approval',
                    'approver_id' => $actorId,
                    'remarks' => 'Approved directly by HR upon submission.',
                    'approved_date' => now(),
                ]);
            }

            return $leave;
        });

        if ($leave->status === 'approved') {
            $this->pdfService->generateIfAvailable($leave, $actorId);
        }

        return $leave;
    }

    /** @param array<string, mixed> $values */
    public function syncHandoverDetails(LeaveRequest $leave, array $values): void
    {
        $hasEmployeeHandover = ! empty($values['handover_person_id']);
        $fields = [
            'handover_person_id' => ['Handover Person ID', $values['handover_person_id'] ?? null],
            'handover_description' => ['Handover Description', $values['handover_description'] ?? null],
            'handover_other_name' => ['Other Handover Name', $hasEmployeeHandover ? null : ($values['handover_other_name'] ?? null)],
            'handover_other_organisation' => ['Other Handover Organisation', $hasEmployeeHandover ? null : ($values['handover_other_organisation'] ?? null)],
            'handover_other_designation' => ['Other Handover Designation', $hasEmployeeHandover ? null : ($values['handover_other_designation'] ?? null)],
            'handover_other_contact_number' => ['Other Handover Contact Number', $hasEmployeeHandover ? null : ($values['handover_other_contact_number'] ?? null)],
        ];

        foreach ($fields as $key => [$name, $value]) {
            $value = trim((string) $value);
            if ($value === '') {
                $leave->details()->where('field_key', $key)->delete();
            } else {
                $leave->details()->updateOrCreate(['field_key' => $key], ['field_name' => $name, 'field_value' => $value]);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function storeSupportingDetails(LeaveRequest $leave, array $data, int $actorId): void
    {
        $certificate = $data['certificate'] ?? null;
        if ($certificate instanceof UploadedFile) {
            $leave->files()->create([
                'file_path' => $certificate->store('leave-certificates', 'public'),
                'original_name' => $certificate->getClientOriginalName(),
                'mime_type' => $certificate->getMimeType(),
                'size' => $certificate->getSize(),
                'uploaded_by' => $actorId,
                'uploaded_date' => now(),
            ]);
        }

        foreach ([
            'signature' => ['signature_path', 'Signature'],
            'handover_giver_signature' => ['handover_giver_signature_path', 'Handover Giver Signature'],
            'handover_receiver_signature' => ['handover_receiver_signature_path', 'Handover Receiver Signature'],
        ] as $input => [$key, $label]) {
            $file = $data[$input] ?? null;
            if ($file instanceof UploadedFile) {
                $leave->details()->create([
                    'field_key' => $key,
                    'field_name' => $label,
                    'field_value' => $file->store('leave-signatures', 'public'),
                ]);
            }
        }

        foreach ([
            ['traveling_outside_country', 'Traveling Outside Country', ! empty($data['traveling_outside_country']) ? 'Yes' : null],
            ['destination', 'Destination', $data['destination'] ?? null],
            ['travel_contact_no', 'Travel Contact No', $data['travel_contact_no'] ?? null],
            ['emergency_contact_no', 'Emergency Contact No', $data['emergency_contact_no'] ?? null],
            ['declaration_text', 'Declaration', $data['declaration'] ?? null],
        ] as [$key, $label, $value]) {
            if ($value !== null && $value !== '') {
                $leave->details()->updateOrCreate(['field_key' => $key], ['field_name' => $label, 'field_value' => $value]);
            }
        }
    }
}
