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

    public function isWeekday(Carbon $date): bool
    {
        return ! $date->isWeekend();
    }
}
