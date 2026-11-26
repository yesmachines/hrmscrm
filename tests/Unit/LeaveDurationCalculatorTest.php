<?php

use App\Services\LeaveDurationCalculator;
use Carbon\Carbon;

test('full-day leave is allocated to each calendar year by weekday', function () {
    $calculator = new LeaveDurationCalculator;

    expect($calculator->allocateByCalendarYear(
        Carbon::parse('2026-12-31'),
        Carbon::parse('2027-01-01'),
        'fullday',
    ))->toBe([2026 => 1.0, 2027 => 1.0]);
});
