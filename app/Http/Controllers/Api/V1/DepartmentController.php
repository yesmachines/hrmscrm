<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\SalesCrm\Department;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use ApiResponse;

    /**
     * Get a listing of departments (with search, filtering, and pagination support).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $query = Department::query()->withCount('employees');

        // Search by name or code
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filter by status (e.g. status=1 for active)
        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        // Filter by organisation_id
        if ($request->filled('organisation_id')) {
            $query->where('organisation_id', (int) $request->input('organisation_id'));
        }

        $sortBy = in_array($request->input('sort_by'), ['name', 'code', 'id', 'created_at'], true)
            ? (string) $request->input('sort_by')
            : 'name';
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        // Return non-paginated listing if requested (useful for dropdowns)
        if ($request->boolean('all') || $request->input('paginate') === 'false') {
            $departments = $query->get();

            return $this->successResponse([
                'departments' => DepartmentResource::collection($departments),
                'total' => $departments->count(),
            ], 'Departments retrieved successfully.');
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->paginate($perPage);

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
