<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\Event;
use App\Models\EventType;
use App\Models\LeaveRequest;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SystemEventSyncService
{
    /**
     * Synchronize all system-generated events for a given year.
     *
     * @return array{anniversaries: int, birthdays: int, new_joiners: int, leaves: int}
     */
    public function syncAll(?int $year = null): array
    {
        $targetYear = $year ?? (int) now()->format('Y');

        return [
            'anniversaries' => $this->syncWorkAnniversaries($targetYear),
            'birthdays' => $this->syncBirthdays($targetYear),
            'new_joiners' => $this->syncNewJoiners($targetYear),
            'leaves' => $this->syncApprovedLeaves($targetYear),
        ];
    }

    /**
     * Sync Work Anniversaries.
     * Only employees who have COMPLETED at least 1 full year are eligible.
     * New joiners or employees joining in the future are strictly excluded.
     */
    public function syncWorkAnniversaries(int $year): int
    {
        $eventType = $this->getOrCreateEventType('WORK_ANNIVERSARY', 'Work Anniversary', 20);

        $employees = Employee::query()
            ->with('user:id,name')
            ->where('status', 1)
            ->where(function ($q): void {
                $q->whereNull('resignation_date')
                    ->orWhereDate('resignation_date', '>', now());
            })
            ->whereNotNull('joining_date')
            ->get();

        $count = 0;

        foreach ($employees as $employee) {
            $joiningDate = Carbon::parse($employee->joining_date);

            // Strict rule: Must already have joined in the past
            if ($joiningDate->isFuture()) {
                continue;
            }

            $joiningYear = (int) $joiningDate->format('Y');
            $yearsCompleted = $year - $joiningYear;

            // Must have completed at least 1 full year
            if ($yearsCompleted < 1) {
                continue;
            }

            $month = (int) $joiningDate->format('m');
            $day = (int) $joiningDate->format('d');

            // Handle leap day (Feb 29) on non-leap years
            if ($month === 2 && $day === 29 && ! Carbon::isLeapYear($year)) {
                $day = 28;
            }

            $anniversaryDate = Carbon::create($year, $month, $day)->setTime(9, 0, 0);

            $name = $employee->user?->name ?? "Employee #{$employee->emp_num}";
            $ordinal = $this->ordinalSuffix($yearsCompleted);
            $title = "{$name}'s {$ordinal} Work Anniversary";
            $description = "Celebrating {$yearsCompleted} year(s) of dedicated service at the company!";

            Event::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'event_type_id' => $eventType->id,
                    'start_datetime' => $anniversaryDate,
                ],
                [
                    'organisation_id' => $employee->organisation_id,
                    'title' => $title,
                    'description' => $description,
                    'end_datetime' => (clone $anniversaryDate)->setTime(18, 0, 0),
                    'status' => 'published',
                    'show_dashboard' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync Birthdays for active employees.
     */
    public function syncBirthdays(int $year): int
    {
        $eventType = $this->getOrCreateEventType('BIRTHDAY', 'Birthday', 10);

        $employees = Employee::query()
            ->with('user:id,name')
            ->where('status', 1)
            ->where(function ($q): void {
                $q->whereNull('resignation_date')
                    ->orWhereDate('resignation_date', '>', now());
            })
            ->get();

        if ($employees->isEmpty()) {
            return 0;
        }

        /** @var Collection<int, EmployeeProfile> $profiles */
        $profiles = Employee::profilesFor($employees);

        $count = 0;

        foreach ($employees as $employee) {
            $profile = $profiles->get($employee->id);
            if (! $profile) {
                continue;
            }

            $dobRaw = $profile->dob_personal ?? $profile->dob_passport;
            if (! $dobRaw) {
                continue;
            }

            $dob = Carbon::parse($dobRaw);
            $month = (int) $dob->format('m');
            $day = (int) $dob->format('d');

            if ($month === 2 && $day === 29 && ! Carbon::isLeapYear($year)) {
                $day = 28;
            }

            $birthdayDate = Carbon::create($year, $month, $day)->setTime(9, 0, 0);

            $name = $employee->user?->name ?? "Employee #{$employee->emp_num}";
            $title = "{$name}'s Birthday";
            $description = "Wishing {$name} a very Happy Birthday!";

            Event::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'event_type_id' => $eventType->id,
                    'start_datetime' => $birthdayDate,
                ],
                [
                    'organisation_id' => $employee->organisation_id,
                    'title' => $title,
                    'description' => $description,
                    'end_datetime' => (clone $birthdayDate)->setTime(18, 0, 0),
                    'status' => 'published',
                    'show_dashboard' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync New Joiners joining on their official start date in the target year.
     * Properly handles cases where HR added the employee ahead of time.
     */
    public function syncNewJoiners(int $year): int
    {
        $eventType = $this->getOrCreateEventType('NEW_JOINER', 'New Joiners', 30);

        $employees = Employee::query()
            ->with('user:id,name')
            ->whereYear('joining_date', $year)
            ->get();

        $count = 0;

        foreach ($employees as $employee) {
            $joinDate = Carbon::parse($employee->joining_date)->setTime(9, 0, 0);

            $name = $employee->user?->name ?? "Employee #{$employee->emp_num}";
            $designation = $employee->designation ? " ({$employee->designation})" : '';
            $title = "New Joiner: {$name}{$designation}";
            $description = "Welcoming {$name} to the team as {$employee->designation}!";

            Event::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'event_type_id' => $eventType->id,
                    'start_datetime' => $joinDate,
                ],
                [
                    'organisation_id' => $employee->organisation_id,
                    'title' => $title,
                    'description' => $description,
                    'end_datetime' => (clone $joinDate)->setTime(18, 0, 0),
                    'status' => 'published',
                    'show_dashboard' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Sync approved employee leaves to the corporate calendar.
     * Allows everyone to see who is on leave today or upcoming.
     */
    public function syncApprovedLeaves(int $year): int
    {
        $leaves = LeaveRequest::query()
            ->with(['employee.user:id,name', 'leaveType:id,leave_name'])
            ->where('status', 'approved')
            ->where(function ($q) use ($year): void {
                $q->whereYear('start_date', $year)
                    ->orWhereYear('end_date', $year);
            })
            ->get();

        $count = 0;

        foreach ($leaves as $leave) {
            if ($this->syncSingleLeave($leave)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sync a single leave request in real-time when it is approved.
     */
    public function syncSingleLeave(LeaveRequest $leave): ?Event
    {
        if ($leave->status !== 'approved') {
            $this->removeLeaveEvent($leave);

            return null;
        }

        $eventType = $this->getOrCreateEventType('EMPLOYEE_ON_LEAVE', 'Employees on Leave', 40);

        $leave->loadMissing(['employee.user:id,name', 'leaveType:id,leave_name']);
        $employee = $leave->employee;
        if (! $employee) {
            return null;
        }

        $name = $employee->user?->name ?? "Employee #{$employee->emp_num}";
        $typeName = $leave->leaveType?->leave_name ?? 'Leave';
        $startDate = Carbon::parse($leave->start_date)->setTime(9, 0, 0);
        $endDate = Carbon::parse($leave->end_date)->setTime(18, 0, 0);

        $title = "{$name} on Leave ({$typeName})";
        $description = "Approved {$typeName} from {$startDate->format('d M Y')} to {$endDate->format('d M Y')}";
        if ($leave->remarks) {
            $description .= "\nRemarks: {$leave->remarks}";
        }

        return Event::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'event_type_id' => $eventType->id,
                'start_datetime' => $startDate,
            ],
            [
                'organisation_id' => $employee->organisation_id,
                'title' => $title,
                'description' => $description,
                'end_datetime' => $endDate,
                'status' => 'published',
                'show_dashboard' => true,
            ]
        );
    }

    /**
     * Remove the matching calendar event when a leave is cancelled, rejected, or deleted.
     */
    public function removeLeaveEvent(LeaveRequest $leave): void
    {
        $eventType = EventType::query()->where('event_code', 'EMPLOYEE_ON_LEAVE')->first();
        if (! $eventType) {
            return;
        }

        $startDate = Carbon::parse($leave->start_date)->setTime(9, 0, 0);

        Event::query()
            ->where('employee_id', $leave->employee_id)
            ->where('event_type_id', $eventType->id)
            ->where('start_datetime', $startDate)
            ->delete();
    }

    /**
     * Get or create a system event type safely.
     */
    protected function getOrCreateEventType(string $code, string $name, int $priority): EventType
    {
        return EventType::query()->firstOrCreate(
            ['event_code' => $code],
            [
                'event_name' => $name,
                'event_source' => 'system',
                'priority' => $priority,
                'status' => 1,
            ]
        );
    }

    /**
     * Format a number with its ordinal suffix (1st, 2nd, 3rd, etc.).
     */
    protected function ordinalSuffix(int $number): string
    {
        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return "{$number}th";
        }

        return $number.$ends[$number % 10];
    }
}
