<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

class LeaveTypeEligibilityService
{
    /**
     * @param  Collection<int, LeaveType>  $leaveTypes
     * @return Collection<int, LeaveType>
     */
    public function filterEligible(
        Collection $leaveTypes,
        ?string $employeeGender,
        ?string $employeeReligion,
        ?string $employeeMaritalStatus = null,
    ): Collection {
        return $leaveTypes
            ->filter(function (LeaveType $leaveType) use ($employeeGender, $employeeReligion, $employeeMaritalStatus): bool {
                try {
                    $this->assertEligible($leaveType, $employeeGender, $employeeReligion, $employeeMaritalStatus);
                } catch (ValidationException) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @throws ValidationException
     */
    public function assertEligible(
        LeaveType $leaveType,
        ?string $employeeGender,
        ?string $employeeReligion,
        ?string $employeeMaritalStatus = null,
    ): void {
        if ($leaveType->status !== 1) {
            throw ValidationException::withMessages([
                'leave_type_id' => ["The selected leave type ({$leaveType->leave_name}) is currently inactive."],
            ]);
        }

        $normalizedEmployeeGender = LeaveType::normalizeGender($employeeGender);
        $requiredGender = $this->requiredGender($leaveType);

        if ($requiredGender !== null && $normalizedEmployeeGender !== $requiredGender) {
            throw ValidationException::withMessages([
                'leave_type_id' => ["{$leaveType->leave_name} is only applicable for {$requiredGender} employees."],
            ]);
        }

        $requiredReligion = $this->requiredReligion($leaveType);
        if ($requiredReligion !== null && ! LeaveType::religionsMatch($employeeReligion, $requiredReligion)) {
            $religionOptions = config('params.leave_type.religion_options', []);
            $religionLabel = is_array($religionOptions)
                ? ($religionOptions[$requiredReligion] ?? $requiredReligion)
                : $requiredReligion;

            throw ValidationException::withMessages([
                'leave_type_id' => ["{$leaveType->leave_name} is only applicable for {$religionLabel} employees."],
            ]);
        }

        $this->assertMaritalStatusEligible($leaveType, $employeeMaritalStatus);
    }

    public function assertMaritalStatusEligible(LeaveType $leaveType, ?string $maritalStatus): void
    {
        if (in_array($leaveType->code, ['MATERNITY', 'PARENTAL'], true)
            && strtolower(trim($maritalStatus ?? '')) !== 'married') {
            throw ValidationException::withMessages([
                'leave_type_id' => ["{$leaveType->leave_name} is only available to employees whose marital status is married."],
            ]);
        }
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  SupportCollection<int, EmployeeProfile>  $profiles
     * @return list<int>
     */
    public function eligibleEmployeeIds(
        LeaveType $leaveType,
        Collection $employees,
        SupportCollection $profiles,
    ): array {
        return array_values($employees->filter(function (Employee $employee) use ($leaveType, $profiles): bool {
            $profile = $profiles->get($employee->id);
            try {
                $this->assertEligible($leaveType, $profile?->gender, $profile?->religion, $profile?->marital_status);
            } catch (ValidationException) {
                return false;
            }

            return true;
        })->map(fn (Employee $employee): int => (int) $employee->id)->all());
    }

    private function requiredGender(LeaveType $leaveType): ?string
    {
        return LeaveType::normalizeGender($leaveType->gender);
    }

    private function requiredReligion(LeaveType $leaveType): ?string
    {
        return LeaveType::normalizeReligion($leaveType->religion);
    }
}
