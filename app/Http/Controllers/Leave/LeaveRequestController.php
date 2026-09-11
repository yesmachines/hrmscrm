<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
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

    public function create(): Response
    {
        $employees = Employee::query()
            ->with([
                'user:id,name',
                'department:id,name',
            ])
            ->select('id', 'user_id', 'emp_num', 'employee_code', 'designation', 'department_id', 'organisation_id')
            ->where('status', 1)
            ->orderBy('id')
            ->get();

        $leaveTypes = LeaveType::query()
            ->select('id', 'leave_name', 'code', 'is_paid', 'requires_attachment', 'status')
            ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ->orderBy('leave_name')
            ->get();

        $currentYear = (int) date('Y');
        $leaveBalances = LeaveBalance::query()
            ->where('year', $currentYear)
            ->select('id', 'employee_id', 'leave_type_id', 'allocated', 'used', 'balance')
            ->get();

        return Inertia::render('leave-requests/create', [
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leaveBalances' => $leaveBalances,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:salescrm.employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'nullable|numeric|min:0.5',
            'status' => 'required|in:applied,approved',
            'remarks' => 'nullable|string',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $totalDays = $validated['total_days'] ?? (
            Carbon::parse($validated['end_date'])->diffInDays(Carbon::parse($validated['start_date'])) + 1
        );

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'],
            'created_by' => $request->user()?->id,
        ]);

        $hrName = $request->user()?->name ?? 'HR';
        $historyRemarks = "Applied by HR ({$hrName}) on behalf of employee.";
        if (! empty($validated['remarks'])) {
            $historyRemarks .= ' Reason: '.$validated['remarks'];
        }

        $leaveRequest->histories()->create([
            'action_type' => $validated['status'],
            'remarks' => $historyRemarks,
            'done_by' => $request->user()?->id,
            'action_on' => now(),
        ]);

        if ($request->hasFile('certificate')) {
            $path = $request->file('certificate')->store('leave-certificates', 'public');

            $leaveRequest->files()->create([
                'file_path' => $path,
                'uploaded_by' => $request->user()?->id,
                'uploaded_date' => now(),
            ]);
        }

        if ($validated['status'] === 'approved') {
            $leaveRequest->approvals()->create([
                'approval_level' => 'HR Direct Approval',
                'approver_id' => $request->user()?->id,
                'remarks' => 'Approved directly by HR upon submission.',
                'approved_date' => now(),
            ]);

            $currentYear = (int) Carbon::parse($validated['start_date'])->format('Y');
            $balance = LeaveBalance::where('employee_id', $validated['employee_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('year', $currentYear)
                ->first();

            if ($balance) {
                $balance->used += $totalDays;
                $balance->balance = max(0, $balance->balance - $totalDays);
                $balance->save();
            }
        }

        return redirect()->route('leave-requests.index')
            ->with('success', 'Leave request submitted successfully on behalf of employee.');
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

        if (in_array($validated['status'], ['approved', 'rejected'])) {
            $leave_request->approvals()->create([
                'approval_level' => 'Final',
                'approver_id' => $request->user()?->id,
                'remarks' => $validated['remarks'] ?? null,
                'approved_date' => now(),
            ]);
        }

        return back()->with('success', 'Leave request status updated successfully.');
    }
}
