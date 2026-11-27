<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Services\LeaveBalanceAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class HrLeaveBalanceController extends Controller
{
    public function __construct(private readonly LeaveBalanceAdjustmentService $leaveBalanceAdjustmentService) {}

    #[OA\Post(
        path: '/api/v1/hr/leave-balances',
        summary: 'Add leave balance for an employee (HR only)',
        description: 'Adds the given quantity to allocated and balance for the employee, leave type and year. Creates the balance row if it does not exist yet. If the leave type\'s policy (for the employee\'s organisation) has carry_forward_weeks configured, leave_validity is set automatically to now plus that many weeks.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['employee_id', 'leave_type_id', 'year', 'allocated'],
            properties: [
                new OA\Property(property: 'employee_id', type: 'integer'),
                new OA\Property(property: 'leave_type_id', type: 'integer'),
                new OA\Property(property: 'year', type: 'integer', example: 2026),
                new OA\Property(property: 'allocated', type: 'number', format: 'float', description: 'Quantity to add', example: 2),
            ],
        )),
        tags: ['HR Leave Balance'],
        responses: [
            new OA\Response(response: 201, description: 'Leave balance added successfully'),
            new OA\Response(response: 403, description: 'HR role required'),
            new OA\Response(response: 422, description: 'Invalid request'),
        ],
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:salescrm.employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000'],
            'allocated' => ['required', 'numeric', 'min:0.01'],
        ]);

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $leaveType = LeaveType::query()->findOrFail($data['leave_type_id']);

        $balance = $this->leaveBalanceAdjustmentService->addBalance(
            $employee,
            $leaveType,
            (int) $data['year'],
            (float) $data['allocated'],
        );

        return $this->successResponse(
            $balance->fresh(['leaveType', 'employee']),
            'Leave balance added successfully.',
            201,
        );
    }
}
