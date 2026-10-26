<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalesCrm\Employee;
use App\Models\Visit;
use App\Services\VisitService;
use App\Support\SalesCrmRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    /**
     * List visit history / requests.
     */
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
