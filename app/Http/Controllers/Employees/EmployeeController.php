<?php

namespace App\Http\Controllers\Employees;

use App\Actions\CreateEmployee;
use App\Actions\DeleteEmployee;
use App\Actions\UpdateEmployee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeProfileRequest;
use App\Http\Requests\Employees\UpdateEmployeeProfileRequest;
use App\Models\Designation;
use App\Models\OfficeLocation;
use App\Models\Organisation;
use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Division;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\EmployeeManager;
use App\Support\SalesCrmRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $search = request('search');

        $employees = Employee::query()
            ->with(['user:id,name,email', 'department:id,name'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('emp_num', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%")
                        ->orWhere('division', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $profiles = Employee::profilesFor($employees->getCollection());
        $countryIds = $profiles->pluck('nationality')
            ->filter(fn ($n) => $n !== null && is_numeric($n))
            ->map(fn ($n) => (int) $n)
            ->unique()
            ->all();

        $countryMap = $countryIds !== []
            ? DB::connection('salescrm')
                ->table('countries')
                ->whereIn('id', $countryIds)
                ->pluck('name', 'id')
                ->all()
            : [];

        $employees->setCollection(
            $employees->getCollection()->map(function (Employee $employee) use ($profiles, $countryMap) {
                $profile = $profiles->get($employee->id);
                $profileData = $profile ? $profile->toArray() : null;

                if ($profileData && ! empty($profileData['nationality']) && is_numeric($profileData['nationality'])) {
                    $profileData['nationality'] = $countryMap[(int) $profileData['nationality']] ?? $profileData['nationality'];
                }

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
                    'profile' => $profileData,
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
            'divisions' => $this->divisions(),
            'designations' => $this->designations(),
            'organisations' => $this->organisations(),
            'officeLocations' => $this->officeLocations(),
            'roles' => $this->roles(),
            'countries' => $this->countries(),
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

        $managers = $employee->managers()
            ->with(['user:id,name,email', 'department:id,name'])
            ->get()
            ->map(function (Employee $mgr) {
                $pivotDeptId = $mgr->pivot?->department_id;
                $dept = $pivotDeptId ? Department::find($pivotDeptId) ?? Division::find($pivotDeptId) : null;

                return [
                    'id' => $mgr->id,
                    'name' => $mgr->user?->name ?? "Employee #{$mgr->id}",
                    'email' => $mgr->user?->email,
                    'emp_num' => $mgr->emp_num,
                    'designation' => $mgr->designation,
                    'division' => $mgr->division,
                    'department_id' => $pivotDeptId,
                    'department' => $dept ? ['id' => $dept->id, 'name' => $dept->name, 'code' => $dept->code ?? null] : null,
                ];
            });

        $subordinates = $employee->subordinates()
            ->with(['user:id,name,email', 'department:id,name'])
            ->get()
            ->map(function (Employee $sub) {
                $pivotDeptId = $sub->pivot?->department_id;
                $dept = $pivotDeptId ? Department::find($pivotDeptId) ?? Division::find($pivotDeptId) : null;

                return [
                    'id' => $sub->id,
                    'name' => $sub->user?->name ?? "Employee #{$sub->id}",
                    'email' => $sub->user?->email,
                    'emp_num' => $sub->emp_num,
                    'designation' => $sub->designation,
                    'division' => $sub->division,
                    'department_id' => $pivotDeptId,
                    'department' => $dept ? ['id' => $dept->id, 'name' => $dept->name, 'code' => $dept->code ?? null] : null,
                ];
            });

        $assignedManagerIds = $managers->pluck('id')->all();

        $availableManagers = Employee::query()
            ->with('user:id,name')
            ->where('status', 1)
            ->where('id', '!=', $employee->id)
            ->whereNotIn('id', $assignedManagerIds)
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $e): array => [
                'id' => $e->id,
                'name' => $e->user?->name ?? "Employee #{$e->id}",
                'designation' => $e->designation,
            ]);

        return Inertia::render('employees/show', [
            'employee' => $this->employeePayload($employee),
            'reportingManagers' => $managers,
            'reportingSubordinates' => $subordinates,
            'availableManagers' => $availableManagers,
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function storeManager(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'manager_id' => ['required', 'integer', 'different:employee', 'exists:salescrm.employees,id'],
            'department_id' => ['nullable', 'integer'],
        ]);

        EmployeeManager::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'manager_id' => (int) $validated['manager_id'],
            ],
            [
                'department_id' => ! empty($validated['department_id']) ? (int) $validated['department_id'] : null,
            ]
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reporting manager assigned successfully.'),
        ]);

        return back();
    }

    public function destroyManager(Employee $employee, int $managerId): RedirectResponse
    {
        EmployeeManager::query()
            ->where('employee_id', $employee->id)
            ->where('manager_id', $managerId)
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reporting manager removed.'),
        ]);

        return back();
    }

    public function edit(Employee $employee): Response
    {
        $employee->load(['user:id,name,email', 'department:id,name']);

        return Inertia::render('employees/edit', [
            'employee' => $this->employeePayload($employee),
            'departments' => $this->departments(),
            'divisions' => $this->divisions(),
            'designations' => $this->designations(),
            'organisations' => $this->organisations(),
            'officeLocations' => $this->officeLocations(),
            'roles' => $this->roles(),
            'countries' => $this->countries(),
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
     * All department options for selection in HRMS (combines HRMS departments and divisions).
     *
     * @return list<array{id: int, name: string, code: ?string}>
     */
    private function departmentOptions(): array
    {
        $depts = Department::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Department $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
            ]);

        $divs = Division::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Division $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
            ]);

        return $depts->concat($divs)->unique('name')->values()->all();
    }

    /**
     * Sales CRM divisions (`cm_divisions`).
     *
     * @return list<array{id: int, name: string, code: string, value: string}>
     */
    private function divisions(): array
    {
        return Division::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Division $division): array => [
                'id' => $division->id,
                'name' => $division->name,
                'code' => $division->code,
                'value' => strtolower($division->code),
            ])
            ->all();
    }

    /**
     * HRMS designations (`designations`).
     *
     * @return list<array{id: int, title: string, shortcode: string, department_id: int}>
     */
    private function designations(): array
    {
        return Designation::query()
            ->where('status', 1)
            ->orderBy('title')
            ->get(['id', 'title', 'shortcode', 'department_id'])
            ->map(fn (Designation $designation): array => [
                'id' => $designation->id,
                'title' => $designation->title,
                'shortcode' => $designation->shortcode,
                'department_id' => $designation->department_id,
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
     * @return array<int, object>
     */
    private function countries(): array
    {
        return DB::connection('salescrm')
            ->table('countries')
            ->where('status', 1)
            ->orderBy('name')
            ->select('id', 'name')
            ->get()
            ->toArray();
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

        $profilePayload = null;
        if ($profile !== null) {
            $nationality = $profile->nationality;
            if ($nationality !== null && is_numeric($nationality)) {
                $nationality = DB::connection('salescrm')
                    ->table('countries')
                    ->where('id', (int) $nationality)
                    ->value('name') ?? $nationality;
            }

            $homeCountryName = null;
            if ($profile->home_country !== null) {
                $homeCountryName = DB::connection('salescrm')
                    ->table('countries')
                    ->where('id', (int) $profile->home_country)
                    ->value('name');
            }

            $profilePayload = array_merge($profile->toArray(), [
                'nationality' => $nationality,
                'home_country_name' => $homeCountryName,
            ]);
        }

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
            'image_url' => $employee->image_url
                ? (str_starts_with($employee->image_url, 'http://') || str_starts_with($employee->image_url, 'https://')
                    ? $employee->image_url
                    : asset('storage/'.ltrim($employee->image_url, '/')))
                : null,
            'image_path' => $employee->image_url,
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
            'profile' => $profilePayload,
        ];
    }
}
