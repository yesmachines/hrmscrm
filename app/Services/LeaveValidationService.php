<?php

namespace App\Services;

use App\Concerns\LeaveBusinessRules;
use App\Models\EmployeeProfile;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\WorkFromHomeSchedule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LeaveValidationService
{
    use LeaveBusinessRules;

    public function __construct(
        private readonly LeaveTypeEligibilityService $leaveTypeEligibilityService,
        private readonly LeaveDurationCalculator $leaveDurationCalculator,
        private readonly FestivalLeaveService $festivalLeaveService,
        private readonly LeaveTypeRules $leaveTypeRules,
        private readonly WorkFromHomeService $workFromHomeService,
    ) {}

    /**
     * Shared order for every active admin/API submission, edit, preview and approval:
     * type, dates/duration and policy date exclusions, overlap, profile, special details,
     * policy limits/department overlap, balance availability, handover, travel, documents,
     * festival eligibility/usage.
     * Rejected/cancelled edits validate dates without reapplying eligibility.
     * Per-type checks live in LeaveBusinessRules; configurable values remain in params.leave_type.
     *
     * @param  array<string, mixed>  $data
     * @return array{leave_type: LeaveType, policy: ?LeavePolicy, start_date: Carbon, end_date: Carbon, request_type: string, total_days: float, attachment_required: bool, remarks: ?string, wfh_schedule: ?WorkFromHomeSchedule}
     */
    public function validateApplication(
        Employee $employee,
        array $data,
        bool $hasFile = false,
        ?int $ignoreLeaveRequestId = null,
        bool $isActiveRequest = true,
        bool $enforceWfhNoticePeriod = true,
    ): array {
        $leaveType = LeaveType::query()->whereKey($data['leave_type_id'] ?? null)->first();
        if (! $leaveType) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['The selected leave type does not exist.'],
            ]);
        }
        if ($isActiveRequest && $leaveType->status !== 1) {
            throw ValidationException::withMessages([
                'leave_type_id' => ["The selected leave type ({$leaveType->leave_name}) is currently inactive."],
            ]);
        }

        $policy = $leaveType->policyForOrganisation($employee->organisation_id);
        $profile = EmployeeProfile::query()->where('employee_id', $employee->id)->first();
        [$startDate, $endDate, $requestType, $totalDays] = $this->validateDates($data, $policy, $profile, $isActiveRequest);
        $attachmentRequired = false;
        $remarks = $data['remarks'] ?? null;
        $workFromHomeSchedule = null;

        if ($isActiveRequest) {
            $this->validateOverlap($employee, $startDate, $endDate, $ignoreLeaveRequestId);
            $profileGender = $profile !== null ? $profile->gender : null;
            $this->leaveTypeEligibilityService->assertEligible(
                $leaveType,
                $profileGender ?? $employee->gender ?? null,
                $profile?->religion,
                $profile?->marital_status,
            );
            $this->validateLeaveTypeDetails($leaveType, [...$data, 'employee_profile' => $profile], $startDate, $totalDays);
            if ($this->workFromHomeService->isWorkFromHomeType($leaveType)) {
                $workFromHomeSchedule = $this->workFromHomeService->validateRequest(
                    $employee,
                    $startDate,
                    $endDate,
                    $totalDays,
                    $ignoreLeaveRequestId,
                    $enforceWfhNoticePeriod,
                );
            } else {
                $this->validatePolicyEligibility($employee, $leaveType, $policy, $startDate, $totalDays, $ignoreLeaveRequestId);
                $this->validateDepartmentOverlap($employee, $leaveType, $policy, $startDate, $endDate, $ignoreLeaveRequestId);
                $this->validateBalance($employee, $leaveType, $policy, $startDate, $totalDays, $ignoreLeaveRequestId);
            }
            $this->validateHandover($employee, $leaveType, $policy, $data, $startDate, $endDate, $totalDays);
            $this->validateTravel($data);
            $attachmentRequired = $this->validateAttachment($leaveType, $policy, $startDate, $endDate, $totalDays, $hasFile);
            $festival = $this->festivalLeaveService->validate(
                $employee, $leaveType, $data['festival_id'] ?? null, $profile,
                $startDate, $endDate, $ignoreLeaveRequestId,
            );
            if ($festival !== null) {
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
            'wfh_schedule' => $workFromHomeSchedule,
        ];
    }

    /**
     * Omitted fields retain saved values; explicit nulls still clear them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function dataForUpdate(LeaveRequest $leaveRequest, array $data): array
    {
        if (isset($data['leave_type_id']) && (int) $data['leave_type_id'] !== (int) $leaveRequest->leave_type_id) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['Leave type cannot be changed after the request is created.'],
            ]);
        }

        $leaveRequest->loadMissing('leaveType');
        $details = $leaveRequest->details->pluck('field_value', 'field_key')->all();
        $travelingOutsideCountry = filter_var(
            $details['traveling_outside_country'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        $annualFields = config('params.leave_type.special_rules.ANNUAL.fields', []);
        $hasAnnualFieldUpdates = array_intersect(array_keys($data), $annualFields) !== [];

        $validationData = array_merge($details, [
            'leave_type_id' => $leaveRequest->leave_type_id,
            'start_date' => Carbon::parse($leaveRequest->start_date)->toDateString(),
            'end_date' => $leaveRequest->end_date !== null ? Carbon::parse($leaveRequest->end_date)->toDateString() : null,
            'request_type' => $leaveRequest->request_type ?? 'fullday',
            'remarks' => $leaveRequest->remarks,
            'traveling_outside_country' => $travelingOutsideCountry,
        ], $data);

        $validationData['_skip_annual_leave_required_fields'] = ! $hasAnnualFieldUpdates
            && $leaveRequest->leaveType?->code === 'ANNUAL';

        if (
            $leaveRequest->leaveType?->code === 'ANNUAL'
            && ! $hasAnnualFieldUpdates
            && ! array_key_exists('traveling_outside_country', $details)
        ) {
            $validationData['traveling_outside_country'] = null;
        }

        if (
            $leaveRequest->leaveType?->code === 'ANNUAL'
            && array_key_exists('traveling_outside_country', $data)
            && ! filter_var($data['traveling_outside_country'], FILTER_VALIDATE_BOOLEAN)
        ) {
            $validationData['destination'] = null;
            $validationData['travel_contact_no'] = null;
        }

        return $validationData;
    }

    public function validatePolicyEligibility(
        Employee $employee,
        LeaveType $leaveType,
        ?LeavePolicy $policy,
        Carbon $startDate,
        float $totalDays,
        ?int $ignoreLeaveRequestId = null,
    ): void {
        $usage = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('status', config('params.leave_request.active_statuses'))
            ->when($ignoreLeaveRequestId !== null, fn ($query) => $query->where('id', '!=', $ignoreLeaveRequestId));

        if ($policy?->allow_once && (clone $usage)->exists()) {
            throw ValidationException::withMessages([
                'leave_type_id' => ["You have already availed {$leaveType->leave_name}. This leave can only be taken once during your service tenure."],
            ]);
        }

        $internalLimit = $this->leaveTypeRules->definition($leaveType)['internal_annual_limit'] ?? null;
        $annualLimit = $internalLimit ?? $policy?->annual_limit;
        if ($annualLimit !== null) {
            $usedDays = (float) (clone $usage)->whereYear('start_date', $startDate->year)->sum('total_days');
            if (($usedDays + $totalDays) > $annualLimit) {
                if ($internalLimit !== null) {
                    throw ValidationException::withMessages([
                        'total_days' => ['This leave request exceeds the permitted allowance. Please contact HR.'],
                    ]);
                }
                $remaining = max(0, $annualLimit - $usedDays);
                throw ValidationException::withMessages([
                    'total_days' => ["This request exceeds your annual limit of {$annualLimit} days for {$leaveType->leave_name}. You have {$remaining} days remaining this year."],
                ]);
            }
        }

        if ($policy?->minimum_service_months !== null && $policy->minimum_service_months > 0) {
            $joiningDate = $employee->joining_date;
            if ($joiningDate === null) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ['Joining date is required to verify minimum service eligibility. Please ask HR to complete your joining date.'],
                ]);
            }
            $eligibleFrom = Carbon::parse($joiningDate)->startOfDay()->addMonthsNoOverflow($policy->minimum_service_months);
            if ($startDate->lt($eligibleFrom)) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["A minimum of {$policy->minimum_service_months} months of service is required to apply for {$leaveType->leave_name}. You are eligible from {$eligibleFrom->toDateString()}."],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function validateLeaveTypeDetails(LeaveType $leaveType, array $data, Carbon $leaveStartDate, float $totalDays): array
    {
        $profile = $data['employee_profile'] ?? null;
        $this->leaveTypeEligibilityService->assertMaritalStatusEligible(
            $leaveType,
            $profile instanceof EmployeeProfile ? $profile->marital_status : null,
        );

        $validator = $this->leaveTypeRules->definition($leaveType)['validator'] ?? null;
        match ($validator) {
            'annual' => $this->validateAnnualLeave($data),
            'unpaid' => $this->validateUnpaidLeave($leaveType, $data),
            'compassionate' => $this->validateCompassionateLeave($data, $totalDays),
            'maternity' => $this->validateMaternityLeave($data),
            'parental' => $this->validateParentalLeave($leaveType, $data['child_birth_date'] ?? null, $leaveStartDate),
            'work_from_home' => $this->validateWorkFromHome($data),
            null, 'festival' => null,
            default => throw new \LogicException("Unknown leave validator [{$validator}]. Check params.leave_type.special_rules."),
        };

        return $this->leaveTypeDetailValues($leaveType, $data);
    }

    private function requiredDetail(string $key, mixed $value): ?string
    {
        $field = config('params.leave_type.detail_fields.'.$key);
        if (! is_string($value) || trim($value) === '') {
            if ($field['required']) {
                throw ValidationException::withMessages([$key => [$field['required_message']]]);
            }

            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function leaveTypeDetailValues(LeaveType $leaveType, array $data): array
    {
        $values = [];
        foreach ($this->leaveTypeRules->fields($leaveType) as $key => $field) {
            $value = $data[$key] ?? null;
            if (in_array($key, ['destination', 'travel_contact_no'], true)
                && array_key_exists('traveling_outside_country', $data)
                && ! filter_var($data['traveling_outside_country'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            if ($key === 'traveling_outside_country') {
                $travelingOutsideCountry = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($travelingOutsideCountry !== null) {
                    $values[$key] = $travelingOutsideCountry ? 'Yes' : 'No';
                }

                continue;
            }
            if ($field['type'] !== 'festival' && is_string($value) && trim($value) !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $data */
    public function syncLeaveTypeDetails(LeaveRequest $leaveRequest, LeaveType $leaveType, array $data): void
    {
        $values = $this->leaveTypeDetailValues($leaveType, $data);
        foreach ($this->leaveTypeRules->fields($leaveType) as $key => $field) {
            if ($field['type'] === 'festival') {
                continue;
            }
            if (array_key_exists($key, $values)) {
                $leaveRequest->details()->updateOrCreate(
                    ['field_key' => $key],
                    ['field_name' => $field['storage_label'] ?? $field['label'], 'field_value' => $values[$key]],
                );
            } else {
                $leaveRequest->details()->where('field_key', $key)->delete();
            }
        }
        $this->festivalLeaveService->syncSelection($leaveRequest, $leaveType, $data['festival_id'] ?? null);
    }

    /** @return list<string> */
    public function detailFields(LeaveType $leaveType): array
    {
        return array_keys($this->leaveTypeRules->fields($leaveType));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{Carbon, Carbon, string, float}
     */
    private function validateDates(array $data, ?LeavePolicy $policy, ?EmployeeProfile $profile, bool $isActiveRequest): array
    {
        if (empty($data['start_date']) || empty($data['end_date'])) {
            throw ValidationException::withMessages(['start_date' => ['Start date and end date are required.']]);
        }
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages(['end_date' => ['End date must be greater than or equal to start date.']]);
        }
        $requestType = $data['request_type'] ?? 'fullday';
        if (! in_array($requestType, config('params.leave_request.request_types'), true)) {
            throw ValidationException::withMessages(['request_type' => ['The selected leave request type is invalid.']]);
        }
        if ($requestType !== 'fullday' && ! $startDate->isSameDay($endDate)) {
            throw ValidationException::withMessages(['request_type' => ['Half-day leave must start and end on the same date.']]);
        }

        $blocksHolidayRequests = $isActiveRequest && ($policy?->blocksRequestsOnOrNearHolidays() ?? false);
        $holidayRangeStart = $startDate->copy();
        $holidayRangeEnd = $endDate->copy();
        if ($blocksHolidayRequests) {
            $holidayRangeStart->subDay();
            $holidayRangeEnd->addDay();
        }
        $holidayDates = $this->festivalLeaveService->holidayDatesForEmployee($profile, $holidayRangeStart, $holidayRangeEnd);

        if ($blocksHolidayRequests && $holidayDates !== []) {
            throw ValidationException::withMessages([
                'start_date' => ['Leave cannot be requested on or immediately adjacent to an applicable holiday.'],
            ]);
        }

        if (
            $isActiveRequest
            && ($policy?->blocksRequestsOnOrNearWeekends() ?? false)
            && $this->containsWeekendOrAdjacentDate($startDate, $endDate)
        ) {
            throw ValidationException::withMessages([
                'start_date' => ['Leave cannot be requested on a weekend or on the day immediately before or after a weekend.'],
            ]);
        }

        if ($requestType !== 'fullday' && ! $this->leaveDurationCalculator->isWeekday($startDate)) {
            throw ValidationException::withMessages(['request_type' => ['Half-day leave must be on a weekday.']]);
        }
        $totalDays = $requestType === 'fullday'
            ? (float) $this->leaveDurationCalculator->countWeekdays($startDate, $endDate, $holidayDates)
            : (in_array($startDate->toDateString(), $holidayDates, true) ? 0.0 : 0.5);
        if ($totalDays < 0.5) {
            throw ValidationException::withMessages(['total_days' => ['Leave duration must be at least 0.5 days.']]);
        }

        return [$startDate, $endDate, $requestType, $totalDays];
    }

    private function containsWeekendOrAdjacentDate(Carbon $startDate, Carbon $endDate): bool
    {
        $date = $startDate->copy()->startOfDay();
        $lastDate = $endDate->copy()->startOfDay();

        while ($date->lte($lastDate)) {
            if (
                ! $this->leaveDurationCalculator->isWeekday($date)
                || ! $this->leaveDurationCalculator->isWeekday($date->copy()->subDay())
                || ! $this->leaveDurationCalculator->isWeekday($date->copy()->addDay())
            ) {
                return true;
            }

            $date->addDay();
        }

        return false;
    }

    private function validateOverlap(Employee $employee, Carbon $startDate, Carbon $endDate, ?int $ignoreLeaveRequestId): void
    {
        $overlap = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', config('params.leave_request.active_statuses'))
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->when($ignoreLeaveRequestId !== null, fn ($query) => $query->where('id', '!=', $ignoreLeaveRequestId))
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => ['You already have an active or approved leave application overlapping with the selected dates.'],
            ]);
        }
    }

    private function validateBalance(Employee $employee, LeaveType $leaveType, ?LeavePolicy $policy, Carbon $startDate, float $totalDays, ?int $ignoreLeaveRequestId): void
    {
        if (! $this->leaveTypeRules->allowsBalance($leaveType, $policy)) {
            return;
        }
        $balance = LeaveBalance::query()
            ->where('leave_type_id', $leaveType->id)
            ->where('employee_id', $employee->id)
            ->where('year', $startDate->year)
            ->first();
        $available = $balance ? (float) $balance->balance : 0.0;
        if ($ignoreLeaveRequestId !== null) {
            $existing = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereYear('start_date', $startDate->year)
                ->where('status', 'approved')
                ->find($ignoreLeaveRequestId);
            // An approved edit can reuse the days already deducted for this request.
            $available += $existing ? (float) $existing->total_days : 0.0;
        }
        // if ($available < $totalDays) {
        //     throw ValidationException::withMessages([
        //         'total_days' => ["Insufficient leave balance for {$leaveType->leave_name}. Available: {$available} days, requested: {$totalDays} days."],
        //     ]);
        // }
    }

    /** @param array<string, mixed> $data */
    private function validateHandover(Employee $employee, LeaveType $leaveType, ?LeavePolicy $policy, array $data, Carbon $startDate, Carbon $endDate, float $totalDays): void
    {
        $handoverId = $data['handover_person_id'] ?? null;
        $required = $this->requiresHandover($leaveType, $policy, $totalDays);
        if (empty($handoverId)) {
            if ($required) {
                $errors = [];
                foreach ([
                    'handover_other_name',
                    'handover_other_organisation',
                    'handover_other_designation',
                    'handover_other_contact_number',
                ] as $field) {
                    if (empty(trim((string) ($data[$field] ?? '')))) {
                        $errors[$field] = ['This field is required for an external handover contact.'];
                    }
                }

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                if (! $this->hasHandoverTasks($data['handover_description'] ?? null)) {
                    throw ValidationException::withMessages([
                        'handover_description' => ['At least one non-empty handover task is required.'],
                    ]);
                }
            }

            return;
        }
        if ((int) $handoverId === (int) $employee->id) {
            throw ValidationException::withMessages(['handover_person_id' => ['You cannot select yourself as the handover person.']]);
        }
        if (! Employee::query()->where('status', 1)->whereKey($handoverId)->exists()) {
            throw ValidationException::withMessages(['handover_person_id' => ['Selected handover employee not found or inactive.']]);
        }
        if (LeaveRequest::query()->where('employee_id', $handoverId)
            ->whereIn('status', config('params.leave_request.active_statuses'))
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())->exists()) {
            throw ValidationException::withMessages(['handover_person_id' => ['The selected handover person is also on leave during the requested dates.']]);
        }
        if ($required && ! $this->hasHandoverTasks($data['handover_description'] ?? null)) {
            throw ValidationException::withMessages(['handover_description' => ['At least one non-empty handover task is required.']]);
        }
    }

    private function hasHandoverTasks(mixed $description): bool
    {
        if (! is_string($description) || trim($description) === '') {
            return false;
        }

        $tasks = json_decode($description);

        if (json_last_error() === JSON_ERROR_NONE && is_array($tasks)) {
            $hasNonEmptyTask = false;
            foreach ($tasks as $task) {
                if (! is_string($task)) {
                    return false;
                }

                $hasNonEmptyTask = $hasNonEmptyTask || trim($task) !== '';
            }

            return $hasNonEmptyTask;
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    private function validateTravel(array $data): void
    {
        if (! filter_var($data['traveling_outside_country'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        if (! is_string($data['destination'] ?? null) || trim($data['destination']) === '') {
            throw ValidationException::withMessages(['destination' => ['Travel destination is required when traveling outside the country.']]);
        }
        if (empty($data['travel_contact_no'])) {
            throw ValidationException::withMessages(['travel_contact_no' => ['Travel contact number is required.']]);
        }
    }

    private function validateAttachment(LeaveType $leaveType, ?LeavePolicy $policy, Carbon $startDate, Carbon $endDate, float $totalDays, bool $hasFile): bool
    {
        $required = $policy?->requiresAttachmentForDuration($totalDays) ?? false;
        $documentAfterDays = $policy?->requires_document_after_days;
        $message = $required
            ? ($documentAfterDays !== null && $documentAfterDays > 0
                ? "A supporting document is required for leaves of {$documentAfterDays} days or more."
                : "Company policy requires a supporting document for {$leaveType->leave_name}.")
            : null;
        if ($policy?->requiresWeekendAttachment($startDate, $endDate)) {
            $required = true;
            $message ??= 'A supporting document is required for leaves spanning across weekends.';
        }
        if ($this->requiresHolidayAttachment($leaveType, $startDate, $endDate)) {
            $required = true;
            $message ??= 'A supporting document is required for leave including or immediately adjacent to a holiday.';
        }
        if ($required && ! $hasFile) {
            throw ValidationException::withMessages(['certificate' => [$message]]);
        }

        return $required;
    }
}
