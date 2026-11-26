<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveApplicationRequest;
use App\Models\LeaveRequest;
use App\Models\SalesCrm\Employee;
use App\Services\LeaveDecisionService;
use App\Services\LeaveSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class HrLeaveController extends Controller
{
    public function __construct(
        private readonly LeaveDecisionService $decisions,
        private readonly LeaveSubmissionService $submissions,
    ) {}

    #[OA\Post(
        path: '/api/v1/hr/leaves/apply',
        summary: 'Apply or apply and approve leave on behalf of an employee (HR only)',
        description: 'Use the employee_id from HR leave metadata and the same application fields as the employee API. Send status=applied to submit or status=approved to apply and approve. WFH is not accepted.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['employee_id', 'leave_type_id', 'start_date', 'end_date', 'status'],
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer'),
                    new OA\Property(property: 'leave_type_id', type: 'integer'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'status', type: 'string', enum: ['applied', 'approved']),
                    new OA\Property(property: 'request_type', type: 'string', enum: ['fullday', 'firsthalf', 'secondhalf']),
                    new OA\Property(property: 'remarks', type: 'string'),
                    new OA\Property(property: 'certificate', type: 'string', format: 'binary'),
                ],
            ),
        )),
        tags: ['Leave Management'],
        responses: [
            new OA\Response(response: 201, description: 'Leave submitted on behalf of the employee'),
            new OA\Response(response: 403, description: 'HR role required'),
            new OA\Response(response: 422, description: 'Invalid application'),
        ],
    )]
    public function apply(LeaveApplicationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $leave = $this->submissions->submitOnBehalf(
            Employee::query()->findOrFail($data['employee_id']),
            $data,
            (int) $actor->id,
            $actor->name,
        );

        return $this->successResponse(
            $leave->load(['leaveType', 'details', 'files', 'histories', 'approvals']),
            'Leave request submitted successfully on behalf of employee.',
            201,
        );
    }

    #[OA\Post(
        path: '/api/v1/hr/leaves/{id}/approve',
        summary: 'Approve an applied non-WFH leave request (HR only)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'remarks', type: 'string')])),
        tags: ['Leave Management'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Leave approved'),
            new OA\Response(response: 403, description: 'HR role required'),
            new OA\Response(response: 404, description: 'Leave request not found'),
            new OA\Response(response: 422, description: 'Leave request cannot be approved'),
        ],
    )]
    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string']]);

        return $this->decide($request, $id, 'approved', $data['remarks'] ?? null);
    }

    #[OA\Post(
        path: '/api/v1/hr/leaves/{id}/reject',
        summary: 'Reject an applied non-WFH leave request (HR only)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['remarks'],
            properties: [new OA\Property(property: 'remarks', type: 'string')],
        )),
        tags: ['Leave Management'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Leave rejected'),
            new OA\Response(response: 403, description: 'HR role required'),
            new OA\Response(response: 404, description: 'Leave request not found'),
            new OA\Response(response: 422, description: 'Remarks required or leave request cannot be rejected'),
        ],
    )]
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string']]);

        return $this->decide($request, $id, 'rejected', $data['remarks']);
    }

    /** @param 'approved'|'rejected' $status */
    private function decide(Request $request, int $id, string $status, ?string $remarks): JsonResponse
    {
        $leave = $this->decisions->decide(LeaveRequest::query()->withoutWorkFromHome(), $id, $status, (int) $request->user()->id, $remarks);
        if ($leave === null) {
            return $this->errorResponse('Leave request not found.', 404);
        }

        return $this->successResponse(
            $leave->load(['leaveType', 'histories', 'approvals']),
            "Leave request {$status} successfully.",
        );
    }
}
