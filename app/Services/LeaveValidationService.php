<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\Festival;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LeaveValidationService
{
    /**
     * Validate all leave type and policy constraints for a leave application.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     leave_type: LeaveType,
     *     policy: LeavePolicy|null,
     *     start_date: Carbon,
     *     end_date: Carbon,
     *     total_days: float,
     *     pay_breakdown: array{full_pay: float, half_pay: float, no_pay: float},
     *     attachment_required: bool,
     *     remarks: string|null
     * }
     *
     * @throws ValidationException
     */
    public function validateApplication(Employee $employee, array $data, bool $hasFile = false): array
    {
        // 1. Leave Type check
        $leaveTypeId = $data['leave_type_id'] ?? null;
        if (! $leaveTypeId) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['Please select a leave type.'],
            ]);
        }

        /** @var LeaveType|null $leaveType */
        $leaveType = LeaveType::with('policy')->find($leaveTypeId);
        if (! $leaveType) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['The selected leave type does not exist.'],
            ]);
        }

        if ($leaveType->status != 1) {
            throw ValidationException::withMessages([
                'leave_type_id' => ["The selected leave type ({$leaveType->leave_name}) is currently inactive."],
            ]);
        }

        // 2. Fetch the SINGLE policy for this leave type
        /** @var LeavePolicy|null $policy */
        $policy = $leaveType->policy ?? LeavePolicy::where('leave_type_id', $leaveType->id)->first();

        // 3. Date & Duration calculation
        if (empty($data['start_date']) || empty($data['end_date'])) {
            throw ValidationException::withMessages([
                'start_date' => ['Start date and end date are required.'],
            ]);
        }

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => ['End date must be greater than or equal to start date.'],
            ]);
        }

        $totalDays = ! empty($data['total_days'])
            ? (float) $data['total_days']
            : (float) ($startDate->diffInDays($endDate) + 1);

        if ($totalDays < 0.5) {
            throw ValidationException::withMessages([
                'total_days' => ['Leave duration must be at least 0.5 days.'],
            ]);
        }

        // 4. Overlapping Active Requests Check
        $overlapping = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['applied', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'start_date' => ['You already have an active or approved leave application overlapping with the selected dates.'],
            ]);
        }

        // 5. Gender Eligibility Check (from Leave Type)
        if (! empty($leaveType->gender) && strtolower($leaveType->gender) !== 'all') {
            $profile = EmployeeProfile::where('employee_id', $employee->id)->first();
            $employeeGender = $profile?->gender ?? $employee->gender ?? null;

            if ($employeeGender && strtolower($employeeGender) !== strtolower($leaveType->gender)) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["{$leaveType->leave_name} is only applicable for {$leaveType->gender} employees."],
                ]);
            }
        }

        // 6. Allow Once in Service Check (from Leave Type)
        if ($leaveType->allow_once) {
            $alreadyUsed = LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereIn('status', ['applied', 'approved'])
                ->exists();

            if ($alreadyUsed) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["You have already availed {$leaveType->leave_name}. This leave can only be taken once during your service tenure."],
                ]);
            }
        }

        // 7. Max Days per Request Check (from Leave Type)
        if ($leaveType->max_days !== null && $totalDays > $leaveType->max_days) {
            throw ValidationException::withMessages([
                'total_days' => ["The requested duration ({$totalDays} days) exceeds the maximum allowed limit of {$leaveType->max_days} days per request for {$leaveType->leave_name}."],
            ]);
        }

        // 8. Annual Limit Check (from Leave Type)
        if ($leaveType->annual_limit !== null) {
            $currentYear = $startDate->year;
            $usedDaysThisYear = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereIn('status', ['applied', 'approved'])
                ->whereYear('start_date', $currentYear)
                ->sum('total_days');

            if (($usedDaysThisYear + $totalDays) > $leaveType->annual_limit) {
                $remaining = max(0, $leaveType->annual_limit - $usedDaysThisYear);
                throw ValidationException::withMessages([
                    'total_days' => ["This request exceeds your annual limit of {$leaveType->annual_limit} days for {$leaveType->leave_name}. You have {$remaining} days remaining this year."],
                ]);
            }
        }

        // 9. Leave Balance Check (from Leave Type allow_balance)
        if ($leaveType->allow_balance) {
            $currentYear = $startDate->year;
            $balance = LeaveBalance::where('leave_type_id', $leaveType->id)
                ->where('employee_id', $employee->id)
                ->where('year', $currentYear)
                ->first();

            $available = $balance ? (float) $balance->balance : 0;
            if ($available < $totalDays) {
                throw ValidationException::withMessages([
                    'total_days' => ["Insufficient leave balance for {$leaveType->leave_name}. Available: {$available} days, requested: {$totalDays} days."],
                ]);
            }
        }

        // 10. Handover Requirement Check (from Leave Type)
        if ($leaveType->requires_handover) {
            $handoverId = $data['handover_person_id'] ?? null;
            if (empty($handoverId)) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['A handover person is required for this leave type.'],
                ]);
            }

            if ((int) $handoverId === (int) $employee->id) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['You cannot select yourself as the handover person.'],
                ]);
            }

            $handoverExists = Employee::where('id', $handoverId)->where('status', 1)->exists();
            if (! $handoverExists) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['Selected handover employee not found or inactive.'],
                ]);
            }

            if (empty($data['handover_description'])) {
                throw ValidationException::withMessages([
                    'handover_description' => ['Handover description / responsibilities are required.'],
                ]);
            }

            if (! empty($data['traveling_outside_country'])) {
                $isTraveling = filter_var($data['traveling_outside_country'], FILTER_VALIDATE_BOOLEAN);
                if ($isTraveling) {
                    if (empty($data['destination'])) {
                        throw ValidationException::withMessages([
                            'destination' => ['Travel destination is required when traveling outside the country.'],
                        ]);
                    }
                    if (empty($data['travel_contact_no'])) {
                        throw ValidationException::withMessages([
                            'travel_contact_no' => ['Travel contact number is required.'],
                        ]);
                    }
                }
            }
        }

        // 11. Minimum Service Months Check (from Policy)
        if ($policy && $policy->minimum_service_months !== null && $policy->minimum_service_months > 0) {
            $joiningDate = $employee->joining_date ?? $employee->created_at;
            if ($joiningDate) {
                $serviceMonths = Carbon::parse($joiningDate)->diffInMonths($startDate);
                if ($serviceMonths < $policy->minimum_service_months) {
                    throw ValidationException::withMessages([
                        'leave_type_id' => ["A minimum of {$policy->minimum_service_months} months of service is required to apply for {$leaveType->leave_name}. Your current tenure is {$serviceMonths} months."],
                    ]);
                }
            }
        }

        // 12. Probation Period Check (from Policy)
        if ($policy && ! $policy->probation_applicable) {
            $employmentStatus = strtolower($employee->employment_status ?? '');
            if (str_contains($employmentStatus, 'probation')) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["{$leaveType->leave_name} is not available during probation period as per company leave policy."],
                ]);
            }
        }

        // 13. Attachment / Document Requirement (Checked together from Type AND Policy)
        $attachmentRequired = false;
        $attachmentMessage = null;

        // Condition A: Leave Type mandates attachment
        if ($leaveType->requires_attachment) {
            $attachmentRequired = true;
            $attachmentMessage = "A supporting document/certificate is mandatory for {$leaveType->leave_name}.";
        }

        // Condition B: Policy mandates attachment
        if ($policy && $policy->requires_attachment) {
            $attachmentRequired = true;
            $attachmentMessage = $attachmentMessage ?? "Company policy requires a supporting document for {$leaveType->leave_name}.";
        }

        // Condition C: Policy document threshold exceeded (requires_document_after_days)
        if ($policy && $policy->requires_document_after_days !== null && $totalDays > $policy->requires_document_after_days) {
            $attachmentRequired = true;
            $attachmentMessage = "Supporting medical certificate is mandatory for leaves exceeding {$policy->requires_document_after_days} days.";
        }

        // Condition D: Policy weekend document requirement (requires_weekend_document)
        if ($policy && $policy->requires_weekend_document) {
            $hasWeekend = false;
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if ($cursor->isWeekend() || $cursor->dayOfWeek === Carbon::FRIDAY) {
                    $hasWeekend = true;
                    break;
                }
                $cursor->addDay();
            }

            if ($hasWeekend) {
                $attachmentRequired = true;
                $attachmentMessage = $attachmentMessage ?? 'A medical certificate is required for leaves spanning across weekends.';
            }
        }

        // Condition E: Special Leave Type checks (Parental, Maternity)
        if ($leaveType->code === 'PARENTAL' && ! empty($data['child_birth_date'])) {
            $childAgeMonths = Carbon::parse($data['child_birth_date'])->diffInMonths(now());
            if ($childAgeMonths >= 6) {
                throw ValidationException::withMessages([
                    'child_birth_date' => ['Child must be under 6 months of age to qualify for Parental Leave.'],
                ]);
            }
        }

        if ($attachmentRequired && ! $hasFile) {
            throw ValidationException::withMessages([
                'certificate' => [$attachmentMessage ?? 'A supporting document/certificate is required for this leave application.'],
            ]);
        }

        // 14. Festival Leave Special Check
        $remarks = $data['remarks'] ?? null;
        if ($leaveType->code === 'FESTIVAL' || stripos($leaveType->leave_name, 'Festival') !== false) {
            if (empty($data['festival_id'])) {
                throw ValidationException::withMessages([
                    'festival_id' => ['Festival selection is required for Festival Leave.'],
                ]);
            }

            $currentYear = $startDate->year;
            $limitReached = LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereYear('start_date', $currentYear)
                ->whereIn('status', ['applied', 'approved'])
                ->exists();

            if ($limitReached) {
                throw ValidationException::withMessages([
                    'festival_id' => ['You have already availed a Festival Leave this year.'],
                ]);
            }

            $festival = Festival::find($data['festival_id']);
            if ($festival) {
                $remarks = 'Festival: '.$festival->name.($remarks ? "\n".$remarks : '');
            }
        }

        // 15. Calculate Pay Breakdown from Policy
        $fullPayDays = 0.0;
        $halfPayDays = 0.0;
        $noPayDays = 0.0;

        if ($policy) {
            $policyFull = (float) ($policy->full_pay_days ?? $totalDays);
            $policyHalf = (float) ($policy->half_pay_days ?? 0);

            $fullPayDays = min($totalDays, $policyFull);
            $halfPayDays = min(max(0.0, $totalDays - $fullPayDays), $policyHalf);
            $noPayDays = max(0.0, $totalDays - $fullPayDays - $halfPayDays);
        } else {
            if ($leaveType->is_paid) {
                $fullPayDays = $totalDays;
            } else {
                $noPayDays = $totalDays;
            }
        }

        return [
            'leave_type' => $leaveType,
            'policy' => $policy,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'pay_breakdown' => [
                'full_pay' => $fullPayDays,
                'half_pay' => $halfPayDays,
                'no_pay' => $noPayDays,
            ],
            'attachment_required' => $attachmentRequired,
            'remarks' => $remarks,
        ];
    }
}
