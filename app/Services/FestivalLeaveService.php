<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\Festival;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Country;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FestivalLeaveService
{
    public function __construct(private readonly LeaveTypeRules $leaveTypeRules) {}

    public function isFestivalLeave(LeaveType $leaveType): bool
    {
        return $this->leaveTypeRules->isFestivalLeave($leaveType);
    }

    public function validate(
        Employee $employee,
        LeaveType $leaveType,
        mixed $festivalId,
        ?EmployeeProfile $profile,
        Carbon $startDate,
        Carbon $endDate,
        ?int $ignoreLeaveRequestId = null,
    ): ?Festival {
        $festival = $this->validateSelection($leaveType, $festivalId, $profile, $startDate, $endDate);

        if ($festival !== null && $this->hasAnnualRequest($employee->id, $leaveType->id, $startDate->year, $ignoreLeaveRequestId)) {
            throw ValidationException::withMessages([
                'festival_id' => ['You have already availed a Festival Leave this year.'],
            ]);
        }

        return $festival;
    }

    public function validateSelection(
        LeaveType $leaveType,
        mixed $festivalId,
        ?EmployeeProfile $profile,
        Carbon $startDate,
        Carbon $endDate,
    ): ?Festival {
        if (! $this->isFestivalLeave($leaveType)) {
            return null;
        }

        if (empty($festivalId)) {
            if (! config('params.leave_type.detail_fields.festival_id.required')) {
                return null;
            }
            throw ValidationException::withMessages([
                'festival_id' => [config('params.leave_type.detail_fields.festival_id.required_message')],
            ]);
        }

        if (! is_int($festivalId) && ! is_string($festivalId)) {
            throw ValidationException::withMessages([
                'festival_id' => ['The selected festival is unavailable.'],
            ]);
        }

        $festival = Festival::query()
            ->where('type', 'festival')
            ->where('is_active', true)
            ->find($festivalId);

        if ($festival === null) {
            throw ValidationException::withMessages([
                'festival_id' => ['The selected festival is unavailable.'],
            ]);
        }

        if (! $this->religionMatches($festival->religion, $profile)) {
            throw ValidationException::withMessages([
                'festival_id' => ["The selected festival ({$festival->name}) is only applicable for {$festival->religion} employees."],
            ]);
        }

        $countryIds = $this->countryIdsForProfiles(collect([$profile])->filter())
            ->first() ?? [];
        $festivalCountryIds = $this->festivalCountryIds(collect([$festival]))->get($festival->id, []);

        if (! $this->nationalityMatches($festivalCountryIds, $countryIds)) {
            throw ValidationException::withMessages([
                'festival_id' => ['The selected festival is not applicable to this employee’s nationality.'],
            ]);
        }

        $lastDate = $festival->end_date ?? $festival->start_date;
        if (
            ($festival->start_date && $startDate->lt($festival->start_date->startOfDay()))
            || ($lastDate && $endDate->gt($lastDate->endOfDay()))
        ) {
            throw ValidationException::withMessages([
                'start_date' => ['The selected leave dates must fall within the selected festival date range.'],
                'end_date' => ['The selected leave dates must fall within the selected festival date range.'],
            ]);
        }

        return $festival;
    }

    public function hasAnnualRequest(int $employeeId, int $leaveTypeId, int $year, ?int $ignoreLeaveRequestId = null): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereYear('start_date', $year)
            ->whereIn('status', config('params.leave_request.active_statuses'))
            ->when($ignoreLeaveRequestId !== null, fn ($query) => $query->where('id', '!=', $ignoreLeaveRequestId))
            ->count() >= config('params.leave_type.special_rules.FESTIVAL.annual_request_limit');
    }

    public function syncSelection(LeaveRequest $leaveRequest, LeaveType $leaveType, mixed $festivalId): void
    {
        if ($this->isFestivalLeave($leaveType) && ! empty($festivalId)) {
            $leaveRequest->details()->updateOrCreate(
                ['field_key' => 'festival_id'],
                ['field_name' => config('params.leave_type.detail_fields.festival_id.storage_label'), 'field_value' => (string) $festivalId],
            );
        } else {
            $leaveRequest->details()->where('field_key', 'festival_id')->delete();
        }
    }

    /**
     * @param  Collection<int, EmployeeProfile>  $profiles
     * @return Collection<int, list<int>>
     */
    public function countryIdsForProfiles(Collection $profiles): Collection
    {
        $countryNames = $profiles
            ->flatMap(fn (EmployeeProfile $profile): array => [$profile->nationality, $profile->home_country])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '' && ! is_numeric($value))
            ->map(fn (string $value): string => trim($value))
            ->unique()
            ->values();

        $countriesByName = $countryNames->isEmpty()
            ? collect()
            : Country::query()
                ->whereIn('name', $countryNames)
                ->get(['id', 'name'])
                ->keyBy(fn (Country $country): string => strtolower($country->name));

        return $profiles->map(function (EmployeeProfile $profile) use ($countriesByName): array {
            $countryIds = [];
            foreach ([$profile->nationality, $profile->home_country] as $value) {
                if (is_numeric($value)) {
                    $countryIds[] = (int) $value;
                } elseif (is_string($value) && trim($value) !== '') {
                    $country = $countriesByName->get(strtolower(trim($value)));
                    if ($country !== null) {
                        $countryIds[] = (int) $country->id;
                    }
                }
            }

            return array_values(array_unique($countryIds));
        });
    }

    /**
     * @return list<string>
     */
    public function holidayDatesForEmployee(?EmployeeProfile $profile, Carbon $startDate, Carbon $endDate): array
    {
        if ($profile === null) {
            return [];
        }

        $holidays = Festival::query()
            ->where('type', 'holiday')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->where(function ($query) use ($startDate) {
                $query->whereDate('end_date', '>=', $startDate->toDateString())
                    ->orWhere(function ($query) use ($startDate) {
                        $query->whereNull('end_date')
                            ->whereDate('start_date', '>=', $startDate->toDateString());
                    });
            })
            ->get(['id', 'religion', 'start_date', 'end_date']);

        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEnd = $endDate->copy()->startOfDay();
        $holidayDates = [];

        foreach ($this->filterEligible($holidays, $profile) as $holiday) {
            if ($holiday->start_date === null) {
                continue;
            }

            $holidayStart = Carbon::parse($holiday->start_date)->startOfDay();
            $holidayEnd = Carbon::parse($holiday->end_date ?? $holiday->start_date)->startOfDay();
            $firstDate = $holidayStart->gt($rangeStart) ? $holidayStart : $rangeStart->copy();
            $lastDate = $holidayEnd->lt($rangeEnd) ? $holidayEnd : $rangeEnd->copy();

            for ($date = $firstDate->copy(); $date->lte($lastDate); $date->addDay()) {
                $holidayDates[$date->toDateString()] = true;
            }
        }

        $dates = array_keys($holidayDates);
        sort($dates);

        return $dates;
    }

    /**
     * @param  Collection<int, Festival|\stdClass>  $festivals
     * @return Collection<int, Festival|\stdClass>
     */
    public function filterEligible(Collection $festivals, ?EmployeeProfile $profile): Collection
    {
        $countryIds = $this->countryIdsForProfiles(collect([$profile])->filter())->first() ?? [];
        $festivalCountries = $this->festivalCountryIds($festivals);

        return $festivals->filter(fn (Festival|\stdClass $festival): bool => $this->religionMatches($festival->religion, $profile)
            && $this->nationalityMatches($festivalCountries->get($festival->id, []), $countryIds));
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, EmployeeProfile>  $profiles
     * @param  Collection<int, list<int>>  $countryIdsByEmployee
     * @return list<array{id: int, name: string, religion: ?string, start_date: ?string, end_date: ?string, country_ids: list<int>, eligible_employee_ids: list<int>}>
     */
    public function optionsForEmployees(Collection $employees, Collection $profiles, Collection $countryIdsByEmployee): array
    {
        $festivals = Festival::query()
            ->where('type', 'festival')
            ->where('is_active', true)
            ->get(['id', 'name', 'religion', 'start_date', 'end_date']);
        $festivalCountries = $this->festivalCountryIds($festivals);

        return array_values($festivals->map(function (Festival $festival) use ($employees, $profiles, $countryIdsByEmployee, $festivalCountries): array {
            $requiredCountryIds = $festivalCountries->get($festival->id, []);

            return [
                'id' => $festival->id,
                'name' => $festival->name,
                'religion' => $festival->religion,
                'start_date' => $festival->start_date?->format('Y-m-d'),
                'end_date' => $festival->end_date?->format('Y-m-d'),
                'country_ids' => $requiredCountryIds,
                'eligible_employee_ids' => array_values($employees->filter(
                    fn (Employee $employee): bool => $this->religionMatches($festival->religion, $profiles->get($employee->id))
                        && $this->nationalityMatches($requiredCountryIds, $countryIdsByEmployee->get($employee->id, [])),
                )->map(fn (Employee $employee): int => $employee->id)->all()),
            ];
        })->all());
    }

    private function religionMatches(?string $religion, ?EmployeeProfile $profile): bool
    {
        return $religion === null || trim($religion) === '' || LeaveType::religionsMatch($profile?->religion, $religion);
    }

    /**
     * @param  list<int>  $requiredCountryIds
     * @param  list<int>  $employeeCountryIds
     */
    private function nationalityMatches(array $requiredCountryIds, array $employeeCountryIds): bool
    {
        return $requiredCountryIds === [] || array_intersect($requiredCountryIds, $employeeCountryIds) !== [];
    }

    /**
     * @param  iterable<Festival|\stdClass>  $festivals
     * @return Collection<int|string, list<int>>
     */
    private function festivalCountryIds(iterable $festivals): Collection
    {
        $festivalIds = [];
        foreach ($festivals as $festival) {
            $festivalIds[] = $festival->id;
        }

        return DB::table('festival_nationality')
            ->whereIn('festival_id', $festivalIds)
            ->get(['festival_id', 'country_id'])
            ->groupBy('festival_id')
            ->map(fn (Collection $countries): array => array_values(array_map('intval', $countries->pluck('country_id')->all())));
    }
}
