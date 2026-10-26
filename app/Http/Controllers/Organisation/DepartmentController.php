<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StoreDepartmentRequest;
use App\Http\Requests\Organisation\UpdateDepartmentRequest;
use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Employee;
use App\Services\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(DepartmentService $departmentService): Response
    {
        $departments = $departmentService->list(
            ['search' => request('search')],
        );
        $departments->withQueryString();

        $departments->setCollection(
            $departments->getCollection()->map(fn (Department $dept): array => $this->departmentPayload($dept))
        );

        return Inertia::render('departments/index', [
            'departments' => $departments,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('departments/create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::query()->create($request->departmentPayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Department created successfully.'),
        ]);

        return to_route('departments.index');
    }

    public function show(Department $department): Response
    {
        $employees = Employee::query()
            ->with('user:id,name,email')
            ->where('department_id', $department->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $emp): array => [
                'id' => $emp->id,
                'name' => $emp->user?->name,
                'email' => $emp->user?->email,
                'emp_num' => $emp->emp_num,
                'designation' => $emp->designation,
                'status' => $emp->status,
            ]);

        return Inertia::render('departments/show', [
            'department' => $this->departmentPayload($department),
            'employees' => $employees,
        ]);
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('departments/edit', [
            'department' => $this->departmentPayload($department),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->departmentPayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Department updated successfully.'),
        ]);

        return to_route('departments.show', $department);
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Department deleted.'),
        ]);

        return to_route('departments.index');
    }

    public function updateStatus(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate(['status' => 'required|in:0,1']);
        $department->update(['status' => (int) $validated['status']]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function departmentPayload(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'status' => (int) $department->status,
            'created_at' => $department->created_at?->format('Y-m-d H:i'),
            'updated_at' => $department->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
