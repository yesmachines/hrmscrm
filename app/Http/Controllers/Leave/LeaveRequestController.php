<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = LeaveRequest::with([
            'employee:id,user_id,employee_code,image_url',
            'employee.user:id,name',
            'leaveType:id,leave_name,is_paid',
        ])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('leave-requests/index', [
            'requests' => $requests,
        ]);
    }

    public function show(LeaveRequest $leave_request)
    {
        $leave_request->load([
            'employee:id,user_id,employee_code,designation,department_id,image_url',
            'employee.user:id,name,email',
            'employee.department:id,name',
            'leaveType:id,leave_name,is_paid',
            'files',
            'histories' => function ($query) {
                $query->orderBy('action_on', 'desc');
            },
            'histories.doneBy:id,name', // Assuming LeaveHistory has doneBy relation to User
        ]);

        return Inertia::render('leave-requests/show', [
            'leave_request' => $leave_request,
        ]);
    }

    public function update(Request $request, LeaveRequest $leave_request)
    {
        $validated = $request->validate([
            'status' => 'required|in:applied,approved,rejected,cancelled',
            'remarks' => 'nullable|string',
        ]);

        $leave_request->update([
            'status' => $validated['status'],
        ]);

        $leave_request->histories()->create([
            'action_type' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'done_by' => $request->user()?->id,
            'action_on' => now(),
        ]);

        return back()->with('success', 'Leave request status updated successfully.');
    }
}
