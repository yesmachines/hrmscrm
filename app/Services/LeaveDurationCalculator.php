<?php

namespace App\Services;

use Carbon\Carbon;

class LeaveDurationCalculator
{
    public function countWeekdays(Carbon $startDate, Carbon $endDate): int
    {
        $cursor = $startDate->copy()->startOfDay();
        $lastDate = $endDate->copy()->startOfDay();
        $weekdays = 0;

        while ($cursor->lte($lastDate)) {
            if ($this->isWeekday($cursor)) {
                $weekdays++;
            }

            $cursor->addDay();
        }

        return $weekdays;
    }

    /**
     * @return array<int, float>
     */
    public function allocateByCalendarYear(Carbon $startDate, Carbon $endDate, string $requestType): array
    {
        if ($requestType !== 'fullday') {
            return [$startDate->year => 0.5];
        }

        $cursor = $startDate->copy()->startOfDay();
        $lastDate = $endDate->copy()->startOfDay();
        $daysByYear = [];

        while ($cursor->lte($lastDate)) {
            if ($this->isWeekday($cursor)) {
                $daysByYear[$cursor->year] = ($daysByYear[$cursor->year] ?? 0.0) + 1.0;
            }

            $cursor->addDay();
        }

        ksort($daysByYear);

        return $daysByYear;
    }

    public function isWeekday(Carbon $date): bool
    {
        return ! $date->isWeekend();
    }
}
