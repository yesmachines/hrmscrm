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
    public function __construct(
        private readonly LeaveTypeEligibilityService $leaveTypeEligibilityService,
        private readonly LeaveDurationCalculator $leaveDurationCalculator,
    ) {}

    public function validatePolicyEligibility(
        Employee $employee,
        LeaveType $leaveType,
        ?LeavePolicy $policy,
        Carbon $startDate,
        float $totalDays,
        ?int $ignoreLeaveRequestId = null,
    ): void {
        if ($policy?->allow_once) {
            $alreadyUsedQuery = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereIn('status', ['applied', 'approved']);

            if ($ignoreLeaveRequestId !== null) {
                $alreadyUsedQuery->where('id', '!=', $ignoreLeaveRequestId);
            }

            if ($alreadyUsedQuery->exists()) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["You have already availed {$leaveType->leave_name}. This leave can only be taken once during your service tenure."],
                ]);
            }
        }

        $annualLimit = $policy?->annual_limit;
        if ($annualLimit !== null) {
            $usedDaysQuery = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereIn('status', ['applied', 'approved'])
                ->whereYear('start_date', $startDate->year);

            if ($ignoreLeaveRequestId !== null) {
                $usedDaysQuery->where('id', '!=', $ignoreLeaveRequestId);
            }

            $usedDaysThisYear = (float) $usedDaysQuery->sum('total_days');

            if (($usedDaysThisYear + $totalDays) > $annualLimit) {
                $remaining = max(0, $annualLimit - $usedDaysThisYear);
                throw ValidationException::withMessages([
                    'total_days' => ["This request exceeds your annual limit of {$annualLimit} days for {$leaveType->leave_name}. You have {$remaining} days remaining this year."],
                ]);
            }
        }

        if ($policy?->minimum_service_months !== null && $policy->minimum_service_months > 0) {
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

        if ($policy && ! $policy->probation_applicable) {
            $employmentStatus = strtolower($employee->employment_status ?? '');
            if (str_contains($employmentStatus, 'probation')) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["{$leaveType->leave_name} is not available during probation period as per company leave policy."],
                ]);
            }
        }
    }

    /**
     * Validate all leave type and policy constraints for a leave application.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     leave_type: LeaveType,
     *     policy: LeavePolicy|null,
     *     start_date: Carbon,
     *     end_date: Carbon,
     *     request_type: string,
     *     total_days: float,
     *     attachment_required: bool,
     *     remarks: string|null
     * }
     *
     * @throws ValidationException
     */
    public function validateApplication(
        Employee $employee,
        array $data,
        bool $hasFile = false,
        ?int $ignoreLeaveRequestId = null,
    ): array {
        // 1. Leave Type check
        $leaveTypeId = $data['leave_type_id'] ?? null;
        if (! $leaveTypeId) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['Please select a leave type.'],
            ]);
        }

        /** @var LeaveType|null $leaveType */
        $leaveType = LeaveType::find($leaveTypeId);
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

        $policy = $leaveType->policyForOrganisation($employee->organisation_id);

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

        $requestType = $data['request_type'] ?? 'fullday';
        if (! in_array($requestType, ['fullday', 'firsthalf', 'secondhalf'], true)) {
            throw ValidationException::withMessages([
                'request_type' => ['The selected leave request type is invalid.'],
            ]);
        }

        if ($requestType !== 'fullday' && ! $startDate->isSameDay($endDate)) {
            throw ValidationException::withMessages([
                'request_type' => ['Half-day leave must start and end on the same date.'],
            ]);
        }

        if ($requestType !== 'fullday' && ! $this->leaveDurationCalculator->isWeekday($startDate)) {
            throw ValidationException::withMessages([
                'request_type' => ['Half-day leave must be on a weekday.'],
            ]);
        }

        $totalDays = $requestType === 'fullday'
            ? (float) $this->leaveDurationCalculator->countWeekdays($startDate, $endDate)
            : 0.5;

        if ($totalDays < 0.5) {
            throw ValidationException::withMessages([
                'total_days' => ['Leave duration must be at least 0.5 days.'],
            ]);
        }

        // 4. Overlapping Active Requests Check
        $startDateValue = $startDate->toDateString();
        $endDateValue = $endDate->toDateString();

        $overlappingQuery = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['applied', 'approved'])
            ->where(function ($query) use ($startDateValue, $endDateValue) {
                $query->whereBetween('start_date', [$startDateValue, $endDateValue])
                    ->orWhereBetween('end_date', [$startDateValue, $endDateValue])
                    ->orWhere(function ($q) use ($startDateValue, $endDateValue) {
                        $q->whereDate('start_date', '<=', $startDateValue)
                            ->whereDate('end_date', '>=', $endDateValue);
                    });
            });

        if ($ignoreLeaveRequestId !== null) {
            $overlappingQuery->where('id', '!=', $ignoreLeaveRequestId);
        }

        $overlapping = $overlappingQuery->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'start_date' => ['You already have an active or approved leave application overlapping with the selected dates.'],
            ]);
        }

        $profile = EmployeeProfile::where('employee_id', $employee->id)->first();
        $rawEmployeeGender = $profile?->gender ?? $employee->gender ?? null;
        $this->leaveTypeEligibilityService->assertEligible($leaveType, $rawEmployeeGender, $profile?->religion);

        $this->validatePolicyEligibility(
            $employee,
            $leaveType,
            $policy,
            $startDate,
            $totalDays,
            $ignoreLeaveRequestId,
        );

        // 8. Leave Balance Check (from Leave Policy)
        if ($policy?->allow_balance ?? true) {
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

        // 9. Handover Requirement Check (from Leave Policy)
        $handoverId = $data['handover_person_id'] ?? null;

        $requiresHandover = $policy?->requiresHandoverForDuration($totalDays) ?? false;

        if ($requiresHandover && empty($handoverId)) {
            throw ValidationException::withMessages([
                'handover_person_id' => ['A handover person is required for this leave type.'],
            ]);
        }

        if (! empty($handoverId)) {
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

            // Check if the selected handover employee is also on leave during the requested dates
            $handoverOnLeave = LeaveRequest::where('employee_id', $handoverId)
                ->whereIn('status', ['applied', 'approved'])
                ->whereDate('start_date', '<=', $endDateValue)
                ->whereDate('end_date', '>=', $startDateValue)
                ->exists();

            if ($handoverOnLeave) {
                throw ValidationException::withMessages([
                    'handover_person_id' => ['The selected handover person is also on leave during the requested dates.'],
                ]);
            }

            if ($requiresHandover && empty($data['handover_description'])) {
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

        $attachmentRequired = $policy?->requiresAttachmentForDuration($totalDays) ?? false;
        $attachmentMessage = $attachmentRequired
            ? ($policy?->requires_document_after_days !== null
                ? "A supporting document is required for leaves exceeding {$policy->requires_document_after_days} days."
                : "Company policy requires a supporting document for {$leaveType->leave_name}.")
            : null;

        if ($policy?->requires_weekend_document) {
            $hasWeekend = false;
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if ($cursor->isWeekend()) {
                    $hasWeekend = true;
                    break;
                }
                $cursor->addDay();
            }

            if ($hasWeekend) {
                $attachmentRequired = true;
                $attachmentMessage ??= 'A supporting document is required for leaves spanning across weekends.';
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

        // 13. Festival Leave Special Check
        $remarks = $data['remarks'] ?? null;
        if ($leaveType->code === 'FESTIVAL' || stripos($leaveType->leave_name, 'Festival') !== false) {
            if (empty($data['festival_id'])) {
                throw ValidationException::withMessages([
                    'festival_id' => ['Festival selection is required for Festival Leave.'],
                ]);
            }

            $currentYear = $startDate->year;
            $limitReachedQuery = LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereYear('start_date', $currentYear)
                ->whereIn('status', ['applied', 'approved']);

            if ($ignoreLeaveRequestId !== null) {
                $limitReachedQuery->where('id', '!=', $ignoreLeaveRequestId);
            }

            $limitReached = $limitReachedQuery->exists();

            if ($limitReached) {
                throw ValidationException::withMessages([
                    'festival_id' => ['You have already availed a Festival Leave this year.'],
                ]);
            }

            $festival = Festival::find($data['festival_id']);
            if ($festival) {
                if (! empty($festival->religion)) {
                    $rawEmployeeReligion = strtolower(trim($profile?->religion ?? ''));
                    $festivalReligion = strtolower(trim($festival->religion));
                    if ($rawEmployeeReligion !== $festivalReligion
                        && ! str_contains($rawEmployeeReligion, $festivalReligion)
                        && ! str_contains($festivalReligion, $rawEmployeeReligion)
                    ) {
                        throw ValidationException::withMessages([
                            'festival_id' => ["The selected festival ({$festival->name}) is only applicable for {$festival->religion} employees."],
                        ]);
                    }
                }
                $remarks = 'Festival: '.$festival->name.($remarks ? "\n".$remarks : '');
            }
        }

        return [
            'leave_type' => $leaveType,
            'policy' => $policy,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'request_type' => $requestType,
            'total_days' => $totalDays,
            'attachment_required' => $attachmentRequired,
            'remarks' => $remarks,
        ];
    }
}
