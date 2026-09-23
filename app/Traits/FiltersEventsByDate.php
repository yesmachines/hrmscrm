<?php

namespace App\Traits;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersEventsByDate
{
    /**
     * Apply date and month filters to an Event query builder.
     *
     * @param  Builder<Event>  $query
     */
    protected function applyDateAndMonthFilters(Builder $query, Request $request): void
    {
        $dateFilter = $request->query('date_filter')
            ?? $request->query('day')
            ?? $request->query('date')
            ?? $request->query('filter')
            ?? $request->input('date_filter')
            ?? $request->input('day')
            ?? $request->input('date')
            ?? $request->input('filter');

        $monthRange = $this->resolveMonthRange($request, $dateFilter ? (string) $dateFilter : null);

        if ($monthRange) {
            [$startOfMonth, $endOfMonth] = $monthRange;

            $query->where(function (Builder $q) use ($startOfMonth, $endOfMonth): void {
                $q->whereBetween('start_datetime', [$startOfMonth, $endOfMonth])
                    ->orWhere(function (Builder $sub) use ($startOfMonth, $endOfMonth): void {
                        $sub->whereNotNull('end_datetime')
                            ->where('start_datetime', '<=', $endOfMonth)
                            ->where('end_datetime', '>=', $startOfMonth);
                    });
            });

            // If dateFilter itself was a month representation (e.g., date_filter=2026-09 or date_filter=this_month),
            // do not proceed to single-day filtering.
            if (! empty($dateFilter) && $this->parseMonthString((string) $dateFilter)) {
                return;
            }
        }

        if (! empty($dateFilter)) {
            $normalized = strtolower(trim(str_replace(['_', '-'], ' ', (string) $dateFilter)));
            $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

            if (in_array($normalized, ['before today', 'before', 'past'])) {
                $today = now()->toDateString();
                $query->whereDate('start_datetime', '<', $today);
            } elseif (in_array($normalized, ['after today', 'after', 'future', 'upcoming'])) {
                $today = now()->toDateString();
                $query->whereDate('start_datetime', '>', $today);
            } else {
                $targetDate = null;
                if (in_array($normalized, ['today', 'current day', 'current'])) {
                    $targetDate = now()->toDateString();
                } elseif (in_array($normalized, ['tomorrow', 'tomorow'])) {
                    $targetDate = now()->addDay()->toDateString();
                } elseif (in_array($normalized, ['day after tomorrow', 'day after tommarow'])) {
                    $targetDate = now()->addDays(2)->toDateString();
                } elseif ($normalized === 'yesterday') {
                    $targetDate = now()->subDay()->toDateString();
                } else {
                    try {
                        $targetDate = Carbon::parse((string) $dateFilter)->toDateString();
                    } catch (\Throwable) {
                        $targetDate = null;
                    }
                }

                if ($targetDate) {
                    $query->where(function (Builder $q) use ($targetDate): void {
                        $q->whereDate('start_datetime', $targetDate)
                            ->orWhere(function (Builder $sub) use ($targetDate): void {
                                $sub->whereNotNull('end_datetime')
                                    ->whereDate('start_datetime', '<=', $targetDate)
                                    ->whereDate('end_datetime', '>=', $targetDate);
                            });
                    });
                }
            }
        }
    }

    /**
     * Resolve start and end of month Carbon instances from request parameters.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    protected function resolveMonthRange(Request $request, ?string $dateFilter = null): ?array
    {
        // 1. Explicit month and year parameters (e.g. month=9&year=2026 or month=September&year=2026)
        if ($request->filled('month') && $request->filled('year')) {
            $monthInput = trim((string) $request->input('month'));
            $yearInput = (int) $request->input('year');

            if (is_numeric($monthInput)) {
                $monthNum = (int) $monthInput;
                if ($monthNum >= 1 && $monthNum <= 12 && $yearInput >= 1970 && $yearInput <= 2100) {
                    $start = Carbon::create($yearInput, $monthNum, 1)->startOfMonth();

                    return [$start, (clone $start)->endOfMonth()];
                }
            } else {
                try {
                    $start = Carbon::parse("{$monthInput} {$yearInput}")->startOfMonth();

                    return [$start, (clone $start)->endOfMonth()];
                } catch (\Throwable) {
                    // continue to next checks
                }
            }
        }

        // 2. Dedicated combined month_year or year_month parameter (e.g. month_year=2026-09)
        $monthYear = $request->query('month_year')
            ?? $request->query('year_month')
            ?? $request->input('month_year')
            ?? $request->input('year_month');

        if (! empty($monthYear)) {
            $range = $this->parseMonthString((string) $monthYear);
            if ($range) {
                return $range;
            }
        }

        // 3. Month parameter alone if it includes year (e.g. month=2026-09 or month=September 2026)
        if ($request->filled('month') && ! $request->filled('year')) {
            $range = $this->parseMonthString(trim((string) $request->input('month')));
            if ($range) {
                return $range;
            }
        }

        // 4. Flexible date_filter parameter if it represents a month or relative month
        if (! empty($dateFilter)) {
            $range = $this->parseMonthString($dateFilter);
            if ($range) {
                return $range;
            }
        }

        return null;
    }

    /**
     * Parse a month/year string into Carbon [startOfMonth, endOfMonth].
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    protected function parseMonthString(string $value): ?array
    {
        $trimmed = trim($value);
        $normalized = strtolower($trimmed);
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        // Relative month keywords
        if (in_array($normalized, ['this month', 'this_month', 'current month', 'current_month'])) {
            $start = now()->startOfMonth();

            return [$start, (clone $start)->endOfMonth()];
        }

        if (in_array($normalized, ['next month', 'next_month'])) {
            $start = now()->addMonth()->startOfMonth();

            return [$start, (clone $start)->endOfMonth()];
        }

        if (in_array($normalized, ['last month', 'last_month', 'previous month', 'previous_month', 'prev month', 'prev_month'])) {
            $start = now()->subMonth()->startOfMonth();

            return [$start, (clone $start)->endOfMonth()];
        }

        // Exact YYYY-MM or YYYY/MM or YYYY_MM (e.g. 2026-09 or 2026-9)
        if (preg_match('/^(\d{4})[-_\/](0?[1-9]|1[0-2])$/', $trimmed, $matches)) {
            $start = Carbon::create((int) $matches[1], (int) $matches[2], 1)->startOfMonth();

            return [$start, (clone $start)->endOfMonth()];
        }

        // Exact MM-YYYY or MM/YYYY or MM_YYYY (e.g. 09-2026 or 9/2026)
        if (preg_match('/^(0?[1-9]|1[0-2])[-_\/](\d{4})$/', $trimmed, $matches)) {
            $start = Carbon::create((int) $matches[2], (int) $matches[1], 1)->startOfMonth();

            return [$start, (clone $start)->endOfMonth()];
        }

        // Month name with 4-digit year (e.g. "September 2026", "sep-2026", "2026 September")
        $monthsPattern = '(?:january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)';
        if (
            preg_match('/^('.$monthsPattern.')[\s\-_]+(\d{4})$/i', $trimmed) ||
            preg_match('/^(\d{4})[\s\-_]+('.$monthsPattern.')$/i', $trimmed)
        ) {
            try {
                $start = Carbon::parse($trimmed)->startOfMonth();

                return [$start, (clone $start)->endOfMonth()];
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
