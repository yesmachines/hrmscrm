<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeManagerRequest;
use App\Http\Requests\Employees\UpdateEmployeeManagerRequest;
use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\EmployeeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeManagerController extends Controller
{
    /**
     * Display a listing of manager assignments.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $departmentId = $request->input('department_id');
        $managerId = $request->input('manager_id');
        $priority = $request->input('priority');
        $tab = $request->input('tab', 'all'); // 'all', 'assigned', 'unassigned'

        $query = Employee::query()
            ->where('status', 1)
            ->with([
                'user:id,name,email',
                'department:id,name,code',
                'reportingManagers' => function ($q) {
                    $q->with([
                        'manager:id,user_id,emp_num,employee_code,designation,division,image_url',
                        'manager.user:id,name,email',
                        'department:id,name,code',
                    ])->orderBy('priority');
                },
            ]);

        if ($tab === 'assigned') {
            $query->has('reportingManagers');
        } elseif ($tab === 'unassigned') {
            $query->doesntHave('reportingManagers');
        }

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('emp_num', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('reportingManagers.manager.user', function (Builder $mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('reportingManagers.department', function (Builder $dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        if ($departmentId !== null && $departmentId !== '') {
            $query->where(function (Builder $q) use ($departmentId) {
                $q->where('department_id', (int) $departmentId)
                    ->orWhereHas('reportingManagers', function (Builder $rq) use ($departmentId) {
                        $rq->where('department_id', (int) $departmentId);
                    });
            });
        }

        if ($managerId !== null && $managerId !== '') {
            $query->whereHas('reportingManagers', function (Builder $rq) use ($managerId) {
                $rq->where('manager_id', (int) $managerId);
            });
        }

        if ($priority !== null && $priority !== '') {
            $query->whereHas('reportingManagers', function (Builder $rq) use ($priority) {
                $rq->where('priority', (int) $priority);
            });
        }

        $employees = $query
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $allEmployees = Employee::query()
            ->where('status', 1)
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get(['id', 'user_id', 'emp_num', 'employee_code', 'designation'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->user?->name ?? "Employee #{$e->emp_num}",
                'email' => $e->user?->email,
                'emp_num' => $e->emp_num,
                'employee_code' => $e->employee_code,
                'designation' => $e->designation,
            ]);

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $totalAssignments = EmployeeManager::query()->count();
        $assignedEmployeesCount = EmployeeManager::query()->distinct('employee_id')->count('employee_id');
        $activeManagersCount = EmployeeManager::query()->distinct('manager_id')->count('manager_id');
        $totalEmployeesCount = Employee::query()->where('status', 1)->count();
        $unassignedCount = max(0, $totalEmployeesCount - $assignedEmployeesCount);

        return Inertia::render('employees/managers/index', [
            'employees' => $employees,
            'allEmployees' => $allEmployees,
            'departments' => $departments,
            'filters' => [
                'search' => $search ?? '',
                'department_id' => $departmentId ?? '',
                'manager_id' => $managerId ?? '',
                'priority' => $priority ?? '',
                'tab' => $tab,
            ],
            'stats' => [
                'total_assignments' => $totalAssignments,
                'assigned_employees' => $assignedEmployeesCount,
                'active_managers' => $activeManagersCount,
                'total_employees' => $totalEmployeesCount,
                'unassigned_employees' => $unassignedCount,
            ],
        ]);
    }

    /**
     * Store a newly created manager assignment.
     */
    public function store(StoreEmployeeManagerRequest $request): RedirectResponse
    {
        $payload = $request->assignmentPayload();

        EmployeeManager::query()->updateOrCreate(
            [
                'employee_id' => $payload['employee_id'],
                'manager_id' => $payload['manager_id'],
            ],
            [
                'department_id' => $payload['department_id'],
                'priority' => $payload['priority'],
            ]
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reporting manager assigned successfully.'),
        ]);

        return back();
    }

    /**
     * Update an existing manager assignment.
     */
    public function update(UpdateEmployeeManagerRequest $request, EmployeeManager $employeeManager): RedirectResponse
    {
        $payload = $request->updatePayload();

        $employeeManager->update($payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Manager assignment updated successfully.'),
        ]);

        return back();
    }

    /**
     * Remove an existing manager assignment.
     */
    public function destroy(EmployeeManager $employeeManager): RedirectResponse
    {
        $employeeManager->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Manager assignment removed successfully.'),
        ]);

        return back();
    }
}
