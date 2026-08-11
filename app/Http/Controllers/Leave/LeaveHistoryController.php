<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveHistory;
use App\Models\LeaveRequest;
use App\Models\SalesCrm\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveHistoryController extends Controller
{
    public function index()
    {
        $histories = LeaveHistory::with([
            'leaveRequest:id,start_date,end_date',
            'doneBy:id,name',
        ])->orderBy('id', 'desc')->paginate(15);

        return Inertia::render('leave-histories/index', [
            'histories' => $histories,
        ]);
    }

    public function create()
    {
        return Inertia::render('leave-histories/create', [
            'leaveRequests' => LeaveRequest::select('id', 'start_date', 'end_date', 'status')->get(),
            'users' => User::select('id', 'name')->where('status', 1)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_request_id' => 'required|exists:leave_requests,id',
            'action_type' => 'required|in:applied,approved,rejected,cancelled',
            'remarks' => 'nullable|string',
            'done_by' => 'nullable|exists:users,id',
            'action_on' => 'nullable|date',
        ]);

        LeaveHistory::create($validated);

        return redirect()->route('leave-histories.index')->with('success', 'Leave history record added successfully.');
    }

    public function edit(LeaveHistory $leave_history)
    {
        return Inertia::render('leave-histories/edit', [
            'leave_history' => $leave_history,
            'leaveRequests' => LeaveRequest::select('id', 'start_date', 'end_date', 'status')->get(),
            'users' => User::select('id', 'name')->where('status', 1)->get(),
        ]);
    }

    public function update(Request $request, LeaveHistory $leave_history)
    {
        $validated = $request->validate([
            'leave_request_id' => 'required|exists:leave_requests,id',
            'action_type' => 'required|in:applied,approved,rejected,cancelled',
            'remarks' => 'nullable|string',
            'done_by' => 'nullable|exists:users,id',
            'action_on' => 'nullable|date',
        ]);

        $leave_history->update($validated);

        return redirect()->route('leave-histories.index')->with('success', 'Leave history record updated successfully.');
    }

    public function destroy(LeaveHistory $leave_history)
    {
        $leave_history->delete();

        return redirect()->route('leave-histories.index')->with('success', 'Leave history record deleted successfully.');
    }
}
