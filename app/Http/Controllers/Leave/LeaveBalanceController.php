<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveBalanceController extends Controller
{
    public function __construct(private readonly LeaveBalanceService $leaveBalanceService) {}

    public function index()
    {
        $balances = LeaveBalance::with([
            'employee:id,user_id,emp_num,employee_code',
            'employee.user:id,name',
            'leaveType:id,leave_name',
        ])->orderBy('id', 'desc')->paginate(15);

        return Inertia::render('leave-balances/index', [
            'balances' => $balances,
        ]);
    }

    public function create()
    {
        return Inertia::render('leave-balances/create', [
            'employees' => Employee::with('user:id,name')->select('id', 'user_id', 'emp_num', 'employee_code')->orderBy('id')->get(),
            'leaveTypes' => LeaveType::select('id', 'leave_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:salescrm.employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'required|integer|min:2000',
            'allocated' => 'required|numeric|min:0',
            'carried_forward' => 'required|numeric|min:0',
            'used' => 'required|numeric|min:0',
            'pending' => 'required|numeric|min:0',
            'encashed' => 'required|numeric|min:0',
            'earned' => 'required|numeric|min:0',
            'remarks' => 'required|string|max:2000',
        ]);

        $this->leaveBalanceService->createOpeningBalance(
            $validated,
            $request->user()?->id,
            $validated['remarks'],
        );

        return redirect()->route('leave-balances.index')->with('success', 'Leave balance added successfully.');
    }

    public function edit(LeaveBalance $leave_balance)
    {
        return Inertia::render('leave-balances/edit', [
            'leave_balance' => $leave_balance->load([
                'employee:id,user_id,emp_num,employee_code',
                'employee.user:id,name',
                'leaveType:id,leave_name',
            ]),
        ]);
    }

    public function update(Request $request, LeaveBalance $leave_balance)
    {
        $validated = $request->validate([
            'allocated' => 'required|numeric|min:0',
            'carried_forward' => 'required|numeric|min:0',
            'used' => 'required|numeric|min:0',
            'pending' => 'required|numeric|min:0',
            'encashed' => 'required|numeric|min:0',
            'earned' => 'required|numeric|min:0',
            'remarks' => 'required|string|max:2000',
        ]);

        $this->leaveBalanceService->updateBalanceComponents(
            $leave_balance,
            $validated,
            $request->user()?->id,
            $validated['remarks'],
        );

        return redirect()->route('leave-balances.index')->with('success', 'Leave balance updated successfully.');
    }

    public function transactions(LeaveBalance $leave_balance)
    {
        $leaveBalance = $leave_balance->load([
            'employee:id,user_id,emp_num,employee_code',
            'employee.user:id,name',
            'leaveType:id,leave_name',
        ]);
        $transactions = $leave_balance->transactions()
            ->with([
                'createdBy:id,name',
                'leaveRequest:id,start_date,end_date,status',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('leave-balances/transactions', [
            'leaveBalance' => $leaveBalance,
            'transactions' => $transactions,
        ]);
    }

    public function destroy(LeaveBalance $leave_balance)
    {
        $yearStart = sprintf('%04d-01-01', $leave_balance->year);
        $yearEnd = sprintf('%04d-12-31', $leave_balance->year);
        $hasLeaveRequests = LeaveRequest::query()
            ->where('employee_id', $leave_balance->employee_id)
            ->where('leave_type_id', $leave_balance->leave_type_id)
            ->whereDate('start_date', '<=', $yearEnd)
            ->where(function ($query) use ($yearStart): void {
                $query->whereDate('end_date', '>=', $yearStart)
                    ->orWhereNull('end_date');
            })
            ->exists();

        if ($leave_balance->transactions()->exists() || $hasLeaveRequests) {
            return back()->withErrors([
                'balance' => 'A leave balance with transaction or request history cannot be deleted.',
            ]);
        }

        $leave_balance->delete();

        return redirect()->route('leave-balances.index')->with('success', 'Leave balance deleted successfully.');
    }
}
