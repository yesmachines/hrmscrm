<?php

namespace App\Services;

use Carbon\Carbon;

class LeaveDurationCalculator
{
    /**
     * @param  list<string>  $excludedDates
     */
    public function countWeekdays(Carbon $startDate, Carbon $endDate, array $excludedDates = []): int
    {
        $cursor = $startDate->copy()->startOfDay();
        $lastDate = $endDate->copy()->startOfDay();
        $excludedDateSet = array_fill_keys($excludedDates, true);
        $weekdays = 0;

        while ($cursor->lte($lastDate)) {
            if ($this->isWeekday($cursor) && ! isset($excludedDateSet[$cursor->toDateString()])) {
                $weekdays++;
            }

            $cursor->addDay();
        }

        return $weekdays;
    }

    public function isWeekday(Carbon $date): bool
    {
        return ! $date->isWeekend();
    }
}
