<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveBalanceController extends Controller
{
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
            'balance' => 'required|numeric|min:0',
        ]);

        LeaveBalance::create($validated);

        return redirect()->route('leave-balances.index')->with('success', 'Leave balance added successfully.');
    }

    public function edit(LeaveBalance $leave_balance)
    {
        return Inertia::render('leave-balances/edit', [
            'leave_balance' => $leave_balance,
            'employees' => Employee::with('user:id,name')->select('id', 'user_id', 'emp_num', 'employee_code')->orderBy('id')->get(),
            'leaveTypes' => LeaveType::select('id', 'leave_name')->get(),
        ]);
    }

    public function update(Request $request, LeaveBalance $leave_balance)
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
            'balance' => 'required|numeric|min:0',
        ]);

        $leave_balance->update($validated);

        return redirect()->route('leave-balances.index')->with('success', 'Leave balance updated successfully.');
    }

    public function destroy(LeaveBalance $leave_balance)
    {
        $leave_balance->delete();

        return redirect()->route('leave-balances.index')->with('success', 'Leave balance deleted successfully.');
    }
}
