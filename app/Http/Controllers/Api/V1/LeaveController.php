<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestFile;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    /**
     * Get the metadata for the leave application screen.
     */
    public function meta(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        // The user ID maps to an Employee in the salescrm DB
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        // Fetch all leave types
        $leaveTypes = LeaveType::all();

        $typesData = $leaveTypes->map(function ($type) use ($employee) {
            // Get policy for this employee's organization
            $policy = LeavePolicy::where('leave_type_id', $type->id)
                ->where('organisation_id', $employee->organisation_id)
                ->first();

            // Get current year balance
            $balance = LeaveBalance::where('leave_type_id', $type->id)
                ->where('employee_id', $employee->id)
                ->where('year', date('Y'))
                ->first();

            return [
                'id' => $type->id,
                'leave_name' => $type->leave_name,
                'requires_attachment' => $type->requires_attachment,
                'balance' => $balance ? [
                    'remaining' => $balance->balance,
                    'used' => $balance->used,
                    'allocated' => $balance->allocated,
                ] : null,
                'policy' => $policy ? [
                    'requires_document_after_days' => $policy->requires_document_after_days,
                    'requires_weekend_document' => $policy->requires_weekend_document,
                    'full_pay_days' => $policy->full_pay_days,
                    'half_pay_days' => $policy->half_pay_days,
                    'no_pay_days' => $policy->no_pay_days,
                ] : null,
            ];
        });

        return $this->successResponse([
            'employee' => [
                'id' => $employee->id,
                'name' => $user->name,
                'image_url' => $employee->image_url,
            ],
            'leave_types' => $typesData,
        ]);
    }

    /**
     * Submit a new leave request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'nullable|numeric|min:0.5',
            'remarks' => 'nullable|string',
            'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        // For simplicity, we trust the client's total_days if provided,
        // otherwise default to a simple diff + 1 (very basic assumption).
        $totalDays = $validated['total_days'] ?? (
            Carbon::parse($validated['end_date'])->diffInDays(Carbon::parse($validated['start_date'])) + 1
        );

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'applied',
            'created_by' => $user->id,
        ]);

        $leaveRequest->histories()->create([
            'action_type' => 'applied',
            'remarks' => 'Leave application submitted via API.',
            'done_by' => $user->id,
            'action_on' => now(),
        ]);

        if ($request->hasFile('certificate')) {
            $path = $request->file('certificate')->store('leave-certificates', 'public');

            LeaveRequestFile::create([
                'leave_request_id' => $leaveRequest->id,
                'file_path' => $path,
                'original_name' => $request->file('certificate')->getClientOriginalName(),
                'mime_type' => $request->file('certificate')->getMimeType(),
                'size' => $request->file('certificate')->getSize(),
                'uploaded_by' => $user->id,
            ]);
        }

        return $this->successResponse($leaveRequest->load('files'), 'Leave request submitted successfully.', 201);
    }

    /**
     * Get the leave history (requests) for the authenticated employee.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (! $employee) {
            return $this->errorResponse('Employee record not found.', 404);
        }

        $query = LeaveRequest::with('leaveType:id,leave_name')
            ->where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc');

        // Optional filters
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }
        
        if ($request->filled('year')) {
            $query->whereYear('created_at', $request->year);
        }
        
        if ($request->filled('month')) {
            $query->whereMonth('created_at', $request->month);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        $leaveRequests = $query->paginate(15);

        return $this->successResponse($leaveRequests);
    }
}
