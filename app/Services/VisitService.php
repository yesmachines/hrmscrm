<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\VisitApproval;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Shared methods are used by Admin and API controllers; change them carefully to preserve both workflows.
 */
class VisitService
{
    /**
     * @return array{visits: LengthAwarePaginator, pending_requests_count: int|null, date_filter: mixed}
     */
    public function list(Request $request, ?int $creatorId = null, bool $includePendingRequestsCount = false): array
    {
        $query = Visit::query()->with([
            'creator:id,user_id,employee_code,emp_num,designation',
            'creator.user:id,name,email',
            'latestApproval.approver:id,name',
        ]);

        if ($creatorId !== null) {
            $query->where('created_by', $creatorId);
        }

        $pendingRequestsCount = $includePendingRequestsCount
            ? (clone $query)->reorder()->where('status', 'pending')->count()
            : null;

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $dateFilter = $request->query('date_filter')
            ?? $request->query('day')
            ?? $request->query('date')
            ?? $request->query('filter')
            ?? $request->input('date_filter')
            ?? $request->input('day')
            ?? $request->input('date')
            ?? $request->input('filter');

        if (! empty($dateFilter)) {
            $normalized = strtolower(trim(str_replace(['_', '-'], ' ', (string) $dateFilter)));
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            if (in_array($normalized, ['before today', 'before', 'past'])) {
                $today = now()->toDateString();
                $query->where(function ($q) use ($today) {
                    $q->where(function ($sub) use ($today) {
                        $sub->whereNotNull('expected_end_date')
                            ->whereDate('expected_end_date', '<', $today);
                    })->orWhere(function ($sub) use ($today) {
                        $sub->whereNull('expected_end_date')
                            ->whereDate('expected_start_date', '<', $today);
                    });
                });
            } elseif (in_array($normalized, ['after today', 'after', 'future', 'upcoming'])) {
                $today = now()->toDateString();
                $query->whereDate('expected_start_date', '>', $today);
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
                        $targetDate = Carbon::parse($dateFilter)->toDateString();
                    } catch (\Throwable) {
                        $targetDate = null;
                    }
                }

                if ($targetDate) {
                    $query->where(function ($q) use ($targetDate) {
                        $q->whereDate('expected_start_date', $targetDate)
                            ->orWhere(function ($sub) use ($targetDate) {
                                $sub->whereNotNull('expected_end_date')
                                    ->whereDate('expected_start_date', '<=', $targetDate)
                                    ->whereDate('expected_end_date', '>=', $targetDate);
                            });
                    });
                }
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->toDateString();
            $endDate = Carbon::parse($request->input('end_date'))->toDateString();

            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('expected_start_date', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNotNull('expected_end_date')
                            ->whereDate('expected_start_date', '<=', $endDate)
                            ->whereDate('expected_end_date', '>=', $startDate);
                    });
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('company', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('visitor_details', 'like', "%{$search}%");
            });
        }

        return [
            'visits' => $query->orderBy('expected_start_date', 'desc')->paginate(15),
            'pending_requests_count' => $pendingRequestsCount,
            'date_filter' => $dateFilter,
        ];
    }

    public function approve(Visit $visit, int $approverId, ?string $instructions): VisitApproval
    {
        $visit->update(['status' => 'approved']);

        return $visit->approvals()->create([
            'approver_id' => $approverId,
            'instructions' => $instructions,
        ]);
    }

    public function reject(Visit $visit, int $approverId, string $reason): VisitApproval
    {
        $visit->update(['status' => 'rejected']);

        return $visit->approvals()->create([
            'approver_id' => $approverId,
            'rejected_reason' => $reason,
        ]);
    }

    public function updateStatus(Visit $visit, string $status): Visit
    {
        $visit->update(['status' => $status]);

        return $visit;
    }
}
