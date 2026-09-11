<?php

namespace App\Http\Controllers\Visits;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Visit::with([
            'creator:id,user_id,employee_code,emp_num,designation',
            'creator.user:id,name',
            'latestApproval.approver:id,name',
        ]);

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

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('company', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('visitor_details', 'like', "%{$search}%");
            });
        }

        $visits = $query->orderBy('expected_start_date', 'desc')->paginate(15);

        return Inertia::render('visits/index', [
            'visits' => $visits,
            'filters' => [
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
                'date_filter' => $dateFilter ?? '',
            ],
        ]);
    }

    public function show(Visit $visit): Response
    {
        $visit->load([
            'creator:id,user_id,employee_code,emp_num,designation',
            'creator.user:id,name,email',
            'approvals' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'approvals.approver:id,name',
        ]);

        return Inertia::render('visits/show', [
            'visit' => $visit,
        ]);
    }

    public function approve(Request $request, Visit $visit): RedirectResponse
    {
        $validated = $request->validate([
            'instructions' => 'nullable|string',
        ]);

        $visit->update([
            'status' => 'approved',
        ]);

        $visit->approvals()->create([
            'approver_id' => $request->user()?->id,
            'instructions' => $validated['instructions'] ?? null,
        ]);

        return back()->with('success', 'Visit request approved successfully.');
    }

    public function reject(Request $request, Visit $visit): RedirectResponse
    {
        $validated = $request->validate([
            'rejected_reason' => 'required|string',
        ]);

        $visit->update([
            'status' => 'rejected',
        ]);

        $visit->approvals()->create([
            'approver_id' => $request->user()?->id,
            'rejected_reason' => $validated['rejected_reason'],
        ]);

        return back()->with('success', 'Visit request rejected.');
    }

    public function updateStatus(Request $request, Visit $visit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:completed,cancelled',
        ]);

        $visit->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Visit status updated to '.$validated['status'].'.');
    }
}
