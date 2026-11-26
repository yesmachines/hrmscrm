<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreWorkFromHomeScheduleRequest;
use App\Models\LeaveHistory;
use App\Models\LeaveRequest;
use App\Models\SalesCrm\Employee;
use App\Models\WorkFromHomeSchedule;
use App\Services\LeavePdfGenerationService;
use App\Services\LeaveValidationService;
use App\Services\WorkFromHomeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
    description: 'Lists WFH requests (Work From Home leave type) of all employees. Today HR manages these requests in the CRM Leave module.',
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
    description: 'Returns the request with its approval and history entries from the Leave module history tables.',
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
    description: 'Approves an applied request. Pending days move to used days against the schedule allowance. Fails if the schedule is inactive.',
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
    description: 'Rejects an applied request. Its pending days are released back to the schedule.',
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

        $schedule = $this->workFromHomeService->updateScheduleStatus(
            $schedule,
            (int) $validated['status'],
            $request->user()?->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'WFH schedule status updated successfully.',
            'schedule' => $this->scheduleData($schedule),
        ]);
    }

    public function requestsIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:salescrm.employees,id'],
            'status' => ['nullable', Rule::in(['applied', 'approved', 'rejected', 'cancelled'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $requests = $this->workFromHomeRequests()
            ->with(['employee.user:id,name', 'details'])
            ->when(isset($filters['employee_id']), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['start_date']), fn ($query) => $query->whereDate('end_date', '>=', $filters['start_date']))
            ->when(isset($filters['end_date']), fn ($query) => $query->whereDate('start_date', '<=', $filters['end_date']))
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'requests' => $requests->through(fn (LeaveRequest $leaveRequest): array => $this->requestData($leaveRequest)),
        ]);
    }

    public function requestsShow(int $id): JsonResponse
    {
        $leaveRequest = $this->findWorkFromHomeRequest($id, ['histories']);

        if ($leaveRequest === null) {
            return $this->requestNotFound();
        }

        return response()->json([
            'success' => true,
            'request' => $this->requestData($leaveRequest),
            'histories' => $leaveRequest->histories
                ->sortByDesc('action_on')
                ->values()
                ->map(fn (LeaveHistory $history): array => [
                    'action_type' => $history->action_type,
                    'remarks' => $history->remarks,
                    'action_on' => $history->action_on?->toIso8601String(),
                ]),
        ]);
    }

    public function approveRequest(Request $request, int $id, LeaveValidationService $leaveValidationService): JsonResponse
    {
        $validated = $request->validate(['remarks' => ['nullable', 'string']]);

        return $this->decideRequest($request, $id, 'approved', $validated['remarks'] ?? null, $leaveValidationService);
    }

    public function rejectRequest(Request $request, int $id, LeaveValidationService $leaveValidationService): JsonResponse
    {
        $validated = $request->validate(['remarks' => ['required', 'string']]);

        return $this->decideRequest($request, $id, 'rejected', $validated['remarks'], $leaveValidationService);
    }

    private function decideRequest(
        Request $request,
        int $id,
        string $status,
        ?string $remarks,
        LeaveValidationService $leaveValidationService,
    ): JsonResponse {
        $leaveRequest = DB::transaction(function () use ($request, $id, $status, $remarks, $leaveValidationService): ?LeaveRequest {
            $leaveRequest = $this->workFromHomeRequests()->whereKey($id)->lockForUpdate()->first();

            if ($leaveRequest === null) {
                return null;
            }

            if ($leaveRequest->status !== 'applied') {
                throw ValidationException::withMessages([
                    'status' => ['Only applied WFH requests can be '.$status.'.'],
                ]);
            }

            $scheduleId = $this->workFromHomeService->scheduleIdForRequest($leaveRequest);
            if ($status === 'approved') {
                $validation = $leaveValidationService->validateApplication(
                    Employee::query()->findOrFail($leaveRequest->employee_id),
                    $leaveValidationService->dataForUpdate($leaveRequest, []),
                    $leaveRequest->files()->exists(),
                    (int) $leaveRequest->id,
                    true,
                    false,
                );
                $schedule = $validation['wfh_schedule'] ?? null;
                if ($schedule !== null) {
                    $this->workFromHomeService->linkRequestToSchedule($leaveRequest, $schedule);
                    $scheduleId = (int) $schedule->id;
                }
            }

            $this->workFromHomeService->applyRequestBalanceTransition(
                $leaveRequest,
                $leaveRequest->status,
                (float) $leaveRequest->total_days,
                $this->workFromHomeService->scheduleIdForRequest($leaveRequest),
                $status,
                (float) $leaveRequest->total_days,
                $scheduleId,
            );

            $leaveRequest->update(['status' => $status]);
            $leaveRequest->histories()->create([
                'action_type' => $status,
                'remarks' => $remarks,
                'done_by' => $request->user()->id,
                'action_on' => now(),
            ]);
            $leaveRequest->approvals()->create([
                'approval_level' => 'Final',
                'approver_id' => $request->user()->id,
                'remarks' => $remarks,
                'approved_date' => now(),
            ]);

            return $leaveRequest;
        });

        if ($leaveRequest === null) {
            return $this->requestNotFound();
        }

        if ($status === 'approved') {
            app(LeavePdfGenerationService::class)->generateIfAvailable($leaveRequest, $request->user()->id);
        }

        $leaveRequest->load(['employee.user:id,name', 'details']);

        return response()->json([
            'success' => true,
            'message' => 'WFH request '.$status.' successfully.',
            'request' => $this->requestData($leaveRequest),
        ]);
    }

    /**
     * @return Builder<LeaveRequest>
     */
    private function workFromHomeRequests(): Builder
    {
        return LeaveRequest::query()->whereHas(
            'leaveType',
            fn (Builder $query) => $query->where('code', WorkFromHomeService::LEAVE_TYPE_CODE),
        );
    }

    /**
     * @param  list<string>  $relations
     */
    private function findWorkFromHomeRequest(int $id, array $relations = []): ?LeaveRequest
    {
        return $this->workFromHomeRequests()
            ->with(['employee.user:id,name', 'details', ...$relations])
            ->find($id);
    }

    private function requestNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'WFH request not found.',
        ], 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(LeaveRequest $leaveRequest): array
    {
        $scheduleId = $leaveRequest->details
            ->firstWhere('field_key', WorkFromHomeService::SCHEDULE_DETAIL_KEY)?->field_value;

        return [
            'id' => $leaveRequest->id,
            'employee_id' => $leaveRequest->employee_id,
            'employee_name' => $leaveRequest->employee?->user?->name,
            'wfh_schedule_id' => is_numeric($scheduleId) ? (int) $scheduleId : null,
            'start_date' => $leaveRequest->start_date?->toDateString(),
            'end_date' => $leaveRequest->end_date?->toDateString(),
            'request_type' => $leaveRequest->request_type,
            'total_days' => (float) $leaveRequest->total_days,
            'status' => $leaveRequest->status,
            'remarks' => $leaveRequest->remarks,
        ];
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
