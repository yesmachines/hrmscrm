<?php

namespace App\Concerns;

use App\Models\Festival;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

trait LeaveBusinessRules
{
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
