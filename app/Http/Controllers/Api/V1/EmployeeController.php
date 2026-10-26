<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Department;
use App\Models\SalesCrm\Division;
use App\Models\SalesCrm\Employee;
use App\Services\EmployeeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class EmployeeController extends Controller
{
    /**
     * Get a listing of employees (with search, filtering, and pagination support).
     */
    #[OA\Get(
        path: '/api/v1/employees',
        summary: 'Get employees list with search and filters',
        security: [['sanctum' => []]],
        tags: ['Employees'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'department_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'exclude_me', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'with_profile', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'all', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employees retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request, EmployeeService $employeeService): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        // Return non-paginated list if requested (e.g. for dropdowns)
        $all = $request->boolean('all') || $request->input('paginate') === 'false';
        $employees = $employeeService->list(
            array_merge(
                $request->only(['search', 'department_id', 'status', 'type', 'sort_by', 'sort_order']),
                ['exclude_me' => $request->boolean('exclude_me')],
            ),
            (int) $request->input('per_page', 15),
            $all,
            (int) $user->id,
        );

        if ($employees instanceof Collection) {
            $data = $this->transformCollection($employees, $request->boolean('with_profile'));

            return $this->successResponse([
                'employees_count' => (int) $employees->count(),
                'employees' => $data,
            ]);
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $employees;

        // Reuse transformCollection which computes profiles and asset counts in bulk
        $transformedItems = $this->transformCollection($paginator->getCollection(), $request->boolean('with_profile'));

        return response()->json([
            'success' => true,
            'data' => [
                'employees_count' => (int) $paginator->total(),
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
    #[OA\Get(
        path: '/api/v1/employees/{id}',
        summary: 'Get employee details by ID',
        security: [['sanctum' => []]],
        tags: ['Employees'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'with_profile', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employee details retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
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
    private function formatEmployee(Employee $emp, ?EmployeeProfile $profile = null, int $assetCount = 0): array
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
            'asset_count' => $assetCount,
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

        // compute asset counts for the collection in bulk
        $employeeIds = $employees->pluck('id')->all();
        $assetCounts = collect();
        if (! empty($employeeIds)) {
            $assetCounts = AssetAssignment::query()
                ->whereIn('assigned_to', $employeeIds)
                ->where('status', 'Assigned')
                ->selectRaw('assigned_to, COUNT(*) AS count')
                ->groupBy('assigned_to')
                ->pluck('count', 'assigned_to');
        }

        return $employees->map(function (Employee $emp) use ($profiles, $withProfile, $assetCounts) {
            $assetCount = isset($assetCounts[$emp->id]) ? (int) $assetCounts[$emp->id] : 0;

            return $this->formatEmployee($emp, $withProfile ? $profiles->get($emp->id) : null, $assetCount);
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
