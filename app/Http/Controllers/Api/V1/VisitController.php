<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalesCrm\Employee;
use App\Models\Visit;
use App\Services\VisitService;
use App\Support\SalesCrmRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class VisitController extends Controller
{
    /**
     * List visit history / requests.
     */
    #[OA\Get(
        path: '/api/v1/visits',
        summary: 'List client/office visits',
        security: [['sanctum' => []]],
        tags: ['Visits'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status (pending, approved, rejected)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'all', in: 'query', description: 'Return all visits (HR only)', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Page size', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Visits retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request, VisitService $visitService): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $isHrOrAdmin = SalesCrmRoles::userHasLoginAccess((int) $user->id);

        // Non-HR users can only see visits they created
        $creatorId = null;
        if (! $isHrOrAdmin || (! $request->boolean('all') && ! $request->has('all'))) {
            $creatorId = $employee?->id ?? (int) $user->id;
        }

        $listing = $visitService->list($request, $creatorId, true);

        return $this->successPaginatedResponse(
            $listing['visits'],
            'visits',
            'Success',
            200,
            ['pending_requests_count' => $listing['pending_requests_count']],
        );
    }

    /**
     * Submit a new visit request.
     */
    #[OA\Post(
        path: '/api/v1/visits',
        summary: 'Submit a new visit request',
        security: [['sanctum' => []]],
        tags: ['Visits'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['total_visitors', 'visitor_details', 'company', 'contact_no', 'email', 'purpose', 'location', 'expected_start_date'],
                properties: [
                    new OA\Property(property: 'total_visitors', type: 'integer', example: 2),
                    new OA\Property(
                        property: 'visitor_details',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string', example: 'Alex Smith'),
                                new OA\Property(property: 'designation', type: 'string', example: 'Procurement Specialist'),
                            ]
                        )
                    ),
                    new OA\Property(property: 'company', type: 'string', example: 'Acme Corp'),
                    new OA\Property(property: 'contact_no', type: 'string', example: '+971501234567'),
                    new OA\Property(property: 'email', type: 'string', example: 'alex@acme.com'),
                    new OA\Property(property: 'purpose', type: 'string', example: 'Contract signing & technical review'),
                    new OA\Property(property: 'location', type: 'string', example: 'Head Office - Room 402'),
                    new OA\Property(property: 'expected_start_date', type: 'string', format: 'date-time', example: '2026-11-25 10:00:00'),
                    new OA\Property(property: 'expected_end_date', type: 'string', format: 'date-time', example: '2026-11-25 13:00:00'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Visit request submitted successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $creatorId = $employee ? $employee->id : $user->id;

        $validated = $request->validate([
            'total_visitors' => 'required|integer|min:1',
            'visitor_details' => 'required|array|min:1',
            'visitor_details.*.name' => 'required|string|max:255',
            'visitor_details.*.designation' => 'nullable|string|max:255',
            'company' => 'required|string|max:255',
            'contact_no' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/'],
            'email' => 'required|email|max:255',
            'purpose' => 'required|string',
            'location' => 'required|string|max:255',
            'expected_start_date' => 'required|date',
            'expected_end_date' => 'nullable|date|after_or_equal:expected_start_date',
            'required_approvals' => 'nullable|string',
        ]);

        $visit = Visit::create([
            'total_visitors' => $validated['total_visitors'],
            'visitor_details' => $validated['visitor_details'],
            'company' => $validated['company'],
            'contact_no' => $validated['contact_no'],
            'email' => $validated['email'],
            'purpose' => $validated['purpose'],
            'location' => $validated['location'],
            'expected_start_date' => $validated['expected_start_date'],
            'expected_end_date' => $validated['expected_end_date'] ?? null,
            'created_by' => $creatorId,
            'required_approvals' => $validated['required_approvals'] ?? null,
            'status' => 'pending',
        ]);

        return $this->successResponse([
            'visit' => $visit->load([
                'creator:id,user_id,employee_code,emp_num,designation',
                'creator.user:id,name',
            ]),
        ], 'Visit request submitted successfully.', 201);
    }

    /**
     * Get visit details.
     */
    #[OA\Get(
        path: '/api/v1/visits/{visit}',
        summary: 'Get visit details',
        security: [['sanctum' => []]],
        tags: ['Visits'],
        parameters: [
            new OA\Parameter(name: 'visit', in: 'path', required: true, description: 'Visit ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Visit retrieved successfully'),
            new OA\Response(response: 404, description: 'Visit not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(Visit $visit): JsonResponse
    {
        $visit->load([
            'creator:id,user_id,employee_code,emp_num,designation',
            'creator.user:id,name,email',
            'approvals.approver:id,name',
        ]);

        return $this->successResponse([
            'visit' => $visit,
        ]);
    }

    /**
     * Approve visit request by HR.
     */
    #[OA\Post(
        path: '/api/v1/visits/{visit}/approve',
        summary: 'Approve a visit request',
        security: [['sanctum' => []]],
        tags: ['Visits'],
        parameters: [
            new OA\Parameter(name: 'visit', in: 'path', required: true, description: 'Visit ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'instructions', type: 'string', example: 'Visitor pass issued at reception'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Visit approved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function approve(Request $request, Visit $visit, VisitService $visitService): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'instructions' => 'nullable|string',
        ]);

        $approval = $visitService->approve($visit, (int) $user->id, $validated['instructions'] ?? null);

        return $this->successResponse([
            'visit' => $visit->fresh([
                'creator.user:id,name',
                'latestApproval.approver:id,name',
            ]),
            'approval' => $approval,
        ], 'Visit request approved successfully.');
    }

    /**
     * Reject visit request by HR.
     */
    #[OA\Post(
        path: '/api/v1/visits/{visit}/reject',
        summary: 'Reject a visit request with reason',
        security: [['sanctum' => []]],
        tags: ['Visits'],
        parameters: [
            new OA\Parameter(name: 'visit', in: 'path', required: true, description: 'Visit ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rejected_reason'],
                properties: [
                    new OA\Property(property: 'rejected_reason', type: 'string', example: 'Meeting room unavailable during the specified time'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Visit request rejected'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function reject(Request $request, Visit $visit, VisitService $visitService): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'rejected_reason' => 'required|string',
        ]);

        $approval = $visitService->reject($visit, (int) $user->id, $validated['rejected_reason']);

        return $this->successResponse([
            'visit' => $visit->fresh([
                'creator.user:id,name',
                'latestApproval.approver:id,name',
            ]),
            'approval' => $approval,
        ], 'Visit request rejected.');
    }

    /**
     * Update visit status to completed or cancelled.
     */
    #[OA\Post(
        path: '/api/v1/visits/{visit}/status',
        summary: 'Update visit status (completed, cancelled)',
        security: [['sanctum' => []]],
        tags: ['Visits'],
        parameters: [
            new OA\Parameter(name: 'visit', in: 'path', required: true, description: 'Visit ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['completed', 'cancelled'], example: 'completed'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Visit status updated successfully'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function updateStatus(Request $request, Visit $visit, VisitService $visitService): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:completed,cancelled',
        ]);

        $visitService->updateStatus($visit, $validated['status']);

        return $this->successResponse([
            'visit' => $visit->fresh(),
        ], 'Visit status updated to '.$validated['status'].'.');
    }
}
