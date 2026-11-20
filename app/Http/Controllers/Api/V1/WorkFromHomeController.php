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
