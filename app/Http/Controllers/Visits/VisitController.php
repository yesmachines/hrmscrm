<?php

namespace App\Http\Controllers\Visits;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Services\VisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function index(Request $request, VisitService $visitService): Response
    {
        $listing = $visitService->list($request);

        return Inertia::render('visits/index', [
            'visits' => $listing['visits'],
            'filters' => [
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
                'date_filter' => $listing['date_filter'] ?? '',
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

    public function approve(Request $request, Visit $visit, VisitService $visitService): RedirectResponse
    {
        $validated = $request->validate([
            'instructions' => 'nullable|string',
        ]);

        $visitService->approve($visit, (int) $request->user()?->id, $validated['instructions'] ?? null);

        return back()->with('success', 'Visit request approved successfully.');
    }

    public function reject(Request $request, Visit $visit, VisitService $visitService): RedirectResponse
    {
        $validated = $request->validate([
            'rejected_reason' => 'required|string',
        ]);

        $visitService->reject($visit, (int) $request->user()?->id, $validated['rejected_reason']);

        return back()->with('success', 'Visit request rejected.');
    }

    public function updateStatus(Request $request, Visit $visit, VisitService $visitService): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:completed,cancelled',
        ]);

        $visitService->updateStatus($visit, $validated['status']);

        return back()->with('success', 'Visit status updated to '.$validated['status'].'.');
    }
}
