<?php

namespace App\Services;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Collection;
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
    ): Collection {
        return $leaveTypes
            ->filter(fn (LeaveType $leaveType): bool => $this->isEligible($leaveType, $employeeGender, $employeeReligion))
            ->values();
    }

    /**
     * @throws ValidationException
     */
    public function assertEligible(
        LeaveType $leaveType,
        ?string $employeeGender,
        ?string $employeeReligion,
    ): void {
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
    }

    private function isEligible(
        LeaveType $leaveType,
        ?string $employeeGender,
        ?string $employeeReligion,
    ): bool {
        $requiredGender = $this->requiredGender($leaveType);
        if ($requiredGender !== null && LeaveType::normalizeGender($employeeGender) !== $requiredGender) {
            return false;
        }

        $requiredReligion = $this->requiredReligion($leaveType);

        return $requiredReligion === null || LeaveType::religionsMatch($employeeReligion, $requiredReligion);
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
