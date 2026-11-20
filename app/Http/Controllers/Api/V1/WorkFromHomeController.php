<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreWorkFromHomeScheduleRequest;
use App\Models\SalesCrm\Employee;
use App\Models\WorkFromHomeSchedule;
use App\Services\WorkFromHomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'WfhRequest',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'employee_name', type: 'string', nullable: true),
        new OA\Property(property: 'wfh_schedule_id', type: 'integer', nullable: true),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date'),
        new OA\Property(property: 'request_type', type: 'string', enum: ['fullday', 'firsthalf', 'secondhalf']),
        new OA\Property(property: 'total_days', type: 'number', format: 'float', example: 2),
        new OA\Property(property: 'status', type: 'string', enum: ['applied', 'approved', 'rejected', 'cancelled']),
        new OA\Property(property: 'remarks', type: 'string', nullable: true),
    ]
)]
#[OA\Get(
    path: '/api/v1/hr/wfh/requests',
    summary: 'List all employee WFH requests (HR/Admin)',
    description: 'NOT YET IMPLEMENTED - planned contract. Lists WFH requests (Work From Home leave type) of all employees. Today HR manages these requests in the CRM Leave module.',
    security: [['sanctum' => []]],
    tags: ['Work From Home'],
    parameters: [
        new OA\Parameter(name: 'employee_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['applied', 'approved', 'rejected', 'cancelled'])),
        new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Requests ending on or after this date', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Requests starting on or before this date', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated WFH requests',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(
                        property: 'requests',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/WfhRequest')),
                            new OA\Property(property: 'current_page', type: 'integer'),
                            new OA\Property(property: 'last_page', type: 'integer'),
                            new OA\Property(property: 'per_page', type: 'integer'),
                            new OA\Property(property: 'total', type: 'integer'),
                        ]
                    ),
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Only HR and Admin users can access this resource'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]
#[OA\Get(
    path: '/api/v1/hr/wfh/requests/{id}',
    summary: 'Get a WFH request with its history (HR/Admin)',
    description: 'NOT YET IMPLEMENTED - planned contract. Returns the request with its approval and history entries from the Leave module history tables.',
    security: [['sanctum' => []]],
    tags: ['Work From Home'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'WFH request details',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'request', ref: '#/components/schemas/WfhRequest'),
                    new OA\Property(
                        property: 'histories',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'action_type', type: 'string'),
                                new OA\Property(property: 'remarks', type: 'string', nullable: true),
                                new OA\Property(property: 'action_on', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Only HR and Admin users can access this resource'),
        new OA\Response(response: 404, description: 'WFH request not found'),
    ]
)]
#[OA\Post(
    path: '/api/v1/hr/wfh/requests/{id}/approve',
    summary: 'Approve a WFH request (HR/Admin)',
    description: 'NOT YET IMPLEMENTED - planned contract. Approves an applied request. Pending days move to used days against the schedule allowance. Fails if the schedule is inactive.',
    security: [['sanctum' => []]],
    tags: ['Work From Home'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'remarks', type: 'string', nullable: true),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'WFH request approved',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'WFH request approved successfully.'),
                    new OA\Property(property: 'request', ref: '#/components/schemas/WfhRequest'),
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Only HR and Admin users can access this resource'),
        new OA\Response(response: 404, description: 'WFH request not found'),
        new OA\Response(response: 422, description: 'Request is not in applied status or its schedule is inactive'),
    ]
)]
#[OA\Post(
    path: '/api/v1/hr/wfh/requests/{id}/reject',
    summary: 'Reject a WFH request (HR/Admin)',
    description: 'NOT YET IMPLEMENTED - planned contract. Rejects an applied request. Its pending days are released back to the schedule.',
    security: [['sanctum' => []]],
    tags: ['Work From Home'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['remarks'],
            properties: [
                new OA\Property(property: 'remarks', type: 'string', example: 'Team coverage not available on these dates.'),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'WFH request rejected',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'WFH request rejected successfully.'),
                    new OA\Property(property: 'request', ref: '#/components/schemas/WfhRequest'),
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Only HR and Admin users can access this resource'),
        new OA\Response(response: 404, description: 'WFH request not found'),
        new OA\Response(response: 422, description: 'Remarks are required, or the request is not in applied status'),
    ]
)]
class WorkFromHomeController extends Controller
{
    public function __construct(private readonly WorkFromHomeService $workFromHomeService) {}

    public function index(Request $request): JsonResponse
    {
        $employee = Employee::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($employee === null) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found.',
            ], 404);
        }

        $schedules = WorkFromHomeSchedule::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WorkFromHomeSchedule $schedule): array => $this->scheduleData($schedule));

        return response()->json([
            'success' => true,
            'schedules' => $schedules,
        ]);
    }

    public function hrIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:salescrm.employees,id'],
            'status' => ['nullable', Rule::in([0, 1])],
        ]);

        $schedules = WorkFromHomeSchedule::query()
            ->with(['employee.user', 'employee.department'])
            ->when(isset($filters['employee_id']), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'schedules' => $schedules->through(fn (WorkFromHomeSchedule $schedule): array => $this->scheduleData($schedule)),
        ]);
    }

    public function store(StoreWorkFromHomeScheduleRequest $request): JsonResponse
    {
        $schedule = $this->workFromHomeService->createSchedule(
            $request->validated(),
            (int) $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'WFH schedule created successfully.',
            'schedule' => $this->scheduleData($schedule),
        ], 201);
    }

    public function updateStatus(Request $request, WorkFromHomeSchedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $schedule = $this->workFromHomeService->updateScheduleStatus($schedule, (int) $validated['status']);

        return response()->json([
            'success' => true,
            'message' => 'WFH schedule status updated successfully.',
            'schedule' => $this->scheduleData($schedule),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleData(WorkFromHomeSchedule $schedule): array
    {
        $schedule->loadMissing(['employee.user', 'employee.department']);
        $balance = $this->workFromHomeService->balanceForSchedule($schedule);

        return [
            'id' => $schedule->id,
            'employee_id' => $schedule->employee_id,
            'employee' => $schedule->relationLoaded('employee') && $schedule->employee !== null
                ? [
                    'name' => $schedule->employee->user?->name,
                    'employee_code' => $schedule->employee->employee_code,
                    'department' => $schedule->employee->department?->name,
                ]
                : null,
            'working_days' => $schedule->working_days,
            'requested_date' => $schedule->requested_date?->toDateString(),
            'status' => $schedule->status,
            'effective_from' => $schedule->effective_from?->toDateString(),
            'effective_to' => $schedule->effective_to?->toDateString(),
            'balance' => [
                'allocated' => (float) ($balance?->allocated ?? 0),
                'used' => (float) ($balance?->used ?? 0),
                'balance' => (float) ($balance?->balance ?? 0),
                'pending' => (float) ($balance?->pending ?? 0),
            ],
        ];
    }
}
