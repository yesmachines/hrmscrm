<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Division;
use App\Models\SalesCrm\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    /**
     * Get a listing of employees (with search, filtering, and pagination support).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $query = Employee::query()
            ->with(['user:id,name,email', 'department:id,name']);

        // Filter: Search across multiple employee & user fields
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('emp_num', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%")
                    ->orWhere('division', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter: Department
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        // Filter: Status (e.g., status=1 for active)
        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        // Filter: Exclude current employee (useful for handover person selection)
        if ($request->boolean('exclude_me')) {
            $query->where('user_id', '!=', $user->id);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'asc');
        $allowedSort = ['id', 'emp_num', 'employee_code', 'designation', 'joining_date', 'status'];

        if (in_array($sortBy, $allowedSort, true)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('id', 'asc');
        }

        // Return non-paginated list if requested (e.g. for dropdowns)
        if ($request->boolean('all') || $request->input('paginate') === 'false') {
            $employees = $query->get();
            $data = $this->transformCollection($employees, $request->boolean('with_profile'));

            return $this->successResponse(['employees' => $data]);
        }

        // Default: Paginated response
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $paginator = $query->paginate($perPage);

        $profiles = $request->boolean('with_profile')
            ? Employee::profilesFor($paginator->getCollection())
            : collect();

        $transformedItems = $paginator->getCollection()->map(function (Employee $emp) use ($profiles, $request) {
            return $this->formatEmployee($emp, $request->boolean('with_profile') ? $profiles->get($emp->id) : null);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'employees' => $transformedItems,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'message' => 'Employees retrieved successfully.',
        ]);
    }

    /**
     * Get single employee details.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($id === 'me') {
            $employee = Employee::query()
                ->with(['user:id,name,email', 'department:id,name', 'organisation:id,name'])
                ->where('user_id', $user->id)
                ->first();
        } else {
            $employee = Employee::query()
                ->with(['user:id,name,email', 'department:id,name', 'organisation:id,name'])
                ->find($id);
        }

        if (! $employee) {
            return $this->errorResponse('Employee not found.', 404);
        }

        $profile = EmployeeProfile::where('employee_id', $employee->id)->first();

        // Resolve country name if nationality/home_country is numeric
        $nationalityName = null;
        $homeCountryName = null;

        if ($profile) {
            $countryIds = array_filter([
                is_numeric($profile->nationality) ? (int) $profile->nationality : null,
                $profile->home_country ? (int) $profile->home_country : null,
            ]);

            if (! empty($countryIds)) {
                $countries = DB::connection('salescrm')
                    ->table('countries')
                    ->whereIn('id', $countryIds)
                    ->pluck('name', 'id');

                $nationalityName = is_numeric($profile->nationality)
                    ? ($countries[$profile->nationality] ?? null)
                    : $profile->nationality;

                $homeCountryName = $profile->home_country
                    ? ($countries[$profile->home_country] ?? null)
                    : null;
            } else {
                $nationalityName = $profile->nationality;
            }
        }

        $data = $this->formatEmployee($employee, $profile);

        if ($profile) {
            $data['profile']['nationality_name'] = $nationalityName;
            $data['profile']['home_country_name'] = $homeCountryName;
        }

        // Fetch hierarchy levels via cm_employee_managers
        $managersQuery = $employee->managers()
            ->with(['user:id,name,email', 'department:id,name,code']);

        $subordinatesQuery = $employee->subordinates()
            ->with(['user:id,name,email', 'department:id,name,code']);

        if ($request->filled('department_id')) {
            $filterDeptId = (int) $request->input('department_id');
            $managersQuery->wherePivot('department_id', $filterDeptId);
            $subordinatesQuery->wherePivot('department_id', $filterDeptId);
        }

        $managers = $managersQuery->get();
        $subordinates = $subordinatesQuery->get();

        $topLevel = $this->formatHierarchyList($managers);
        $lowLevel = $this->formatHierarchyList($subordinates);

        $data['top_level'] = $topLevel;
        $data['low_level'] = $lowLevel;

        return $this->successResponse([
            'employee' => $data,
            'top_level' => $topLevel,
            'low_level' => $lowLevel,
        ]);
    }

    /**
     * Format a single employee model.
     */
    private function formatEmployee(Employee $emp, ?EmployeeProfile $profile = null): array
    {
        $item = [
            'id' => $emp->id,
            'user_id' => $emp->user_id,
            'name' => $emp->user?->name,
            'email' => $emp->user?->email,
            'emp_num' => $emp->emp_num,
            'employee_code' => $emp->employee_code,
            'designation' => $emp->designation,
            'division' => $emp->division,
            'phone' => $emp->phone,
            'image_url' => $emp->image_url,
            'status' => $emp->status,
            'employment_status' => $emp->employment_status,
            'joining_date' => $emp->joining_date?->format('Y-m-d'),
            'department' => $emp->department ? [
                'id' => $emp->department->id,
                'name' => $emp->department->name,
            ] : null,
        ];

        if ($profile) {
            $item['profile'] = [
                'gender' => $profile->gender,
                'dob_personal' => $profile->dob_personal?->format('Y-m-d'),
                'marital_status' => $profile->marital_status,
                'nationality' => $profile->nationality,
                'religion' => $profile->religion,
                'blood_group' => $profile->blood_group,
                'personal_email' => $profile->personal_email,
                'personal_mobile' => $profile->personal_mobile,
                'emergency_contact_name' => $profile->emergency_contact_name,
                'emergency_mobile' => $profile->emergency_mobile,
            ];
        }

        return $item;
    }

    /**
     * Transform an Eloquent collection of employees.
     */
    private function transformCollection($employees, bool $withProfile = false): array
    {
        $profiles = $withProfile ? Employee::profilesFor($employees) : collect();

        return $employees->map(function (Employee $emp) use ($profiles, $withProfile) {
            return $this->formatEmployee($emp, $withProfile ? $profiles->get($emp->id) : null);
        })->all();
    }

    /**
     * Format a collection of employees for hierarchy levels (top_level / low_level) with department details.
     *
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>>
     */
    private function formatHierarchyList(Collection $employees): array
    {
        $pivotDeptIds = $employees->pluck('pivot.department_id')->filter()->unique()->all();

        $departmentsMap = ! empty($pivotDeptIds)
            ? Department::query()->whereIn('id', $pivotDeptIds)->get(['id', 'name', 'code'])->keyBy('id')
            : collect();

        $missingDeptIds = array_diff($pivotDeptIds, $departmentsMap->keys()->all());
        if (! empty($missingDeptIds)) {
            $divisions = Division::query()->whereIn('id', $missingDeptIds)->get(['id', 'name', 'code'])->keyBy('id');
            foreach ($divisions as $divId => $div) {
                $departmentsMap->put($divId, $div);
            }
        }

        return $employees->map(function (Employee $emp) use ($departmentsMap) {
            $pivotDeptId = $emp->pivot?->department_id;
            $deptPayload = null;

            if ($pivotDeptId && $departmentsMap->has($pivotDeptId)) {
                $dept = $departmentsMap->get($pivotDeptId);
                $deptPayload = [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'code' => $dept->code ?? null,
                ];
            } elseif ($emp->department) {
                $deptPayload = [
                    'id' => $emp->department->id,
                    'name' => $emp->department->name,
                    'code' => $emp->department->code ?? null,
                ];
            } elseif ($emp->division) {
                $divisionMap = [
                    'sd' => ['id' => 1, 'name' => 'Steel Division', 'code' => 'SD'],
                    'is' => ['id' => 2, 'name' => 'Industrial Solutions', 'code' => 'IS'],
                    'ct' => ['id' => 3, 'name' => 'Cleaning Technology', 'code' => 'YC'],
                    'rf' => ['id' => 4, 'name' => 'Flooring Solutions', 'code' => 'RF'],
                    'stg' => ['id' => 5, 'name' => 'Van Tracking', 'code' => 'STG'],
                    'serv' => ['id' => 6, 'name' => 'Service And Spare Parts', 'code' => 'SS'],
                    'ss' => ['id' => 6, 'name' => 'Service And Spare Parts', 'code' => 'SS'],
                    'ops' => ['id' => null, 'name' => 'Operations', 'code' => 'OPS'],
                ];
                $divKey = strtolower($emp->division);
                $deptPayload = $divisionMap[$divKey] ?? [
                    'id' => null,
                    'name' => ucfirst($emp->division),
                    'code' => strtoupper($emp->division),
                ];
            }

            return [
                'id' => $emp->id,
                'user_id' => $emp->user_id,
                'name' => $emp->user?->name,
                'email' => $emp->user?->email,
                'emp_num' => $emp->emp_num,
                'employee_code' => $emp->employee_code,
                'designation' => $emp->designation,
                'division' => $emp->division,
                'phone' => $emp->phone,
                'image_url' => $emp->image_url,
                'status' => $emp->status,
                'employment_status' => $emp->employment_status,
                'department_id' => $deptPayload['id'] ?? null,
                'department' => $deptPayload,
            ];
        })->values()->all();
    }
}
