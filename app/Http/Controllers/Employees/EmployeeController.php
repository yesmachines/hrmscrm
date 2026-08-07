<?php

namespace App\Http\Controllers\Employees;

use App\Actions\CreateEmployee;
use App\Actions\DeleteEmployee;
use App\Actions\UpdateEmployee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeProfileRequest;
use App\Http\Requests\Employees\UpdateEmployeeProfileRequest;
use App\Models\OfficeLocation;
use App\Models\Organisation;
use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Employee;
use App\Support\SalesCrmRoles;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $employees = Employee::query()
            ->with(['user:id,name,email', 'department:id,name'])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $profiles = Employee::profilesFor($employees->getCollection());

        $employees->setCollection(
            $employees->getCollection()->map(function (Employee $employee) use ($profiles) {
                return [
                    'id' => $employee->id,
                    'emp_num' => $employee->emp_num,
                    'employee_code' => $employee->employee_code,
                    'designation' => $employee->designation,
                    'division' => $employee->division,
                    'phone' => $employee->phone,
                    'employment_status' => $employee->employment_status,
                    'status' => $employee->status,
                    'joining_date' => $employee->joining_date?->format('Y-m-d'),
                    'user' => $employee->user,
                    'department' => $employee->department,
                    'profile' => $profiles->get($employee->id),
                ];
            }),
        );

        return Inertia::render('employees/index', [
            'employees' => $employees,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employees/create', [
            'departments' => $this->departments(),
            'organisations' => $this->organisations(),
            'officeLocations' => $this->officeLocations(),
            'roles' => $this->roles(),
        ]);
    }

    public function store(
        StoreEmployeeProfileRequest $request,
        CreateEmployee $createEmployee,
    ): RedirectResponse {
        [$crmUser, $crmEmployee, $profile, $roles] = $request->employeePayload();

        $employee = $createEmployee->handle($crmUser, $crmEmployee, $profile, $roles);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employee created.'),
        ]);

        return to_route('employees.show', $employee);
    }

    public function show(Employee $employee): Response
    {
        $employee->load(['user:id,name,email', 'department:id,name']);

        return Inertia::render('employees/show', [
            'employee' => $this->employeePayload($employee),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        $employee->load(['user:id,name,email', 'department:id,name']);

        return Inertia::render('employees/edit', [
            'employee' => $this->employeePayload($employee),
            'departments' => $this->departments(),
            'organisations' => $this->organisations(),
            'officeLocations' => $this->officeLocations(),
            'roles' => $this->roles(),
        ]);
    }

    public function update(
        UpdateEmployeeProfileRequest $request,
        Employee $employee,
        UpdateEmployee $updateEmployee,
    ): RedirectResponse {
        [$crmUser, $crmEmployee, $profile, $roles] = $request->employeePayload();

        $updateEmployee->handle($employee, $crmUser, $crmEmployee, $profile, $roles);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employee updated.'),
        ]);

        return to_route('employees.show', $employee);
    }

    public function destroy(
        Employee $employee,
        DeleteEmployee $deleteEmployee,
    ): RedirectResponse {
        $deleteEmployee->handle($employee);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employee deleted.'),
        ]);

        return to_route('employees.index');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function departments(): array
    {
        return Department::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
            ])
            ->all();
    }

    /**
     * HRMS organisations for employee assignment.
     *
     * @return list<array{id: int, name: string, short_name: string}>
     */
    private function organisations(): array
    {
        return Organisation::query()
            ->orderByDesc('status')
            ->orderBy('org_name')
            ->get(['id', 'org_name', 'short_name', 'status'])
            ->map(fn (Organisation $organisation): array => [
                'id' => $organisation->id,
                'name' => $organisation->status === 1
                    ? $organisation->org_name
                    : $organisation->org_name.' (Inactive)',
                'short_name' => $organisation->short_name,
            ])
            ->all();
    }

    /**
     * HRMS office locations for employee assignment.
     *
     * @return list<array{id: int, name: string, organisation_id: int}>
     */
    private function officeLocations(): array
    {
        return OfficeLocation::query()
            ->orderBy('office_name')
            ->get(['id', 'office_name', 'organisation_id', 'city'])
            ->map(fn (OfficeLocation $location): array => [
                'id' => $location->id,
                'name' => $location->city
                    ? "{$location->office_name} ({$location->city})"
                    : $location->office_name,
                'organisation_id' => $location->organisation_id,
            ])
            ->all();
    }

    /**
     * Sales CRM Spatie roles for employee ACL (excludes superadmin).
     *
     * @return list<array{name: string}>
     */
    private function roles(): array
    {
        return SalesCrmRoles::employeeRoleOptions();
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Employee $employee): array
    {
        $profile = $employee->profile();

        $roleNames = $employee->user_id
            ? SalesCrmRoles::roleNamesForUser((int) $employee->user_id)
            : [];

        $organisation = $employee->organisation_id
            ? Organisation::query()
                ->where('id', $employee->organisation_id)
                ->first(['id', 'org_name', 'short_name'])
            : null;

        $officeLocation = $employee->office_location_id
            ? OfficeLocation::query()
                ->where('id', $employee->office_location_id)
                ->first(['id', 'office_name', 'city'])
            : null;

        return [
            'id' => $employee->id,
            'user_id' => $employee->user_id,
            'organisation_id' => $employee->organisation_id,
            'emp_num' => $employee->emp_num,
            'employee_code' => $employee->employee_code,
            'phone' => $employee->phone,
            'designation' => $employee->designation,
            'designation_id' => $employee->designation_id,
            'employment_status' => $employee->employment_status,
            'office_location_id' => $employee->office_location_id,
            'joining_date' => $employee->joining_date?->format('Y-m-d'),
            'resignation_date' => $employee->resignation_date?->format('Y-m-d'),
            'division' => $employee->division,
            'image_url' => $employee->image_url,
            'status' => $employee->status,
            'has_report' => $employee->has_report,
            'department_id' => $employee->department_id,
            'name' => $employee->user?->name,
            'email' => $employee->user?->email,
            'roles' => $roleNames[0] ?? null,
            'user' => $employee->user,
            'department' => $employee->department,
            'organisation' => $organisation
                ? [
                    'id' => $organisation->id,
                    'name' => $organisation->org_name,
                    'short_name' => $organisation->short_name,
                ]
                : null,
            'office_location' => $officeLocation
                ? [
                    'id' => $officeLocation->id,
                    'name' => $officeLocation->office_name,
                    'city' => $officeLocation->city,
                ]
                : null,
            'profile' => $profile,
        ];
    }
}
