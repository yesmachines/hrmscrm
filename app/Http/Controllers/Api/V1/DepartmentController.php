<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\SalesCrm\Department;
use App\Services\DepartmentService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;

class DepartmentController extends Controller
{
    use ApiResponse;

    /**
     * Get a listing of departments (with search, filtering, and pagination support).
     */
    #[OA\Get(
        path: '/api/v1/departments',
        summary: 'List all departments with filtering and pagination',
        security: [['sanctum' => []]],
        tags: ['Departments'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Search department name', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'all', in: 'query', description: 'Return all departments unpaginated (e.g. true for dropdowns)', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Number of items per page', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Departments retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request, DepartmentService $departmentService): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        // Return non-paginated listing if requested (useful for dropdowns)
        $all = $request->boolean('all') || $request->input('paginate') === 'false';
        $departments = $departmentService->list(
            $request->only(['search', 'status', 'organisation_id', 'sort_by', 'sort_dir']),
            (int) $request->input('per_page', 15),
            $all,
            true,
        );

        if ($departments instanceof Collection) {
            return $this->successResponse([
                'departments' => DepartmentResource::collection($departments),
                'total' => $departments->count(),
            ], 'Departments retrieved successfully.');
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $departments;

        return response()->json([
            'statusCode' => 200,
            'message' => 'Departments retrieved successfully.',
            'data' => [
                'departments' => DepartmentResource::collection($paginator->items()),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Get details for a single department.
     */
    #[OA\Get(
        path: '/api/v1/departments/{id}',
        summary: 'Get department details by ID',
        security: [['sanctum' => []]],
        tags: ['Departments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Department ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Department retrieved successfully'),
            new OA\Response(response: 404, description: 'Department not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $department = Department::query()
            ->withCount('employees')
            ->find($id);

        if (! $department) {
            return $this->errorResponse('Department not found.', 404);
        }

        return $this->successResponse([
            'department' => new DepartmentResource($department),
        ], 'Department retrieved successfully.');
    }
}
