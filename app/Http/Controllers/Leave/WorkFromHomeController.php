<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreWorkFromHomeScheduleRequest;
use App\Models\SalesCrm\Employee;
use App\Models\WorkFromHomeSchedule;
use App\Services\WorkFromHomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkFromHomeController extends Controller
{
    public function __construct(private readonly WorkFromHomeService $workFromHomeService) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:salescrm.employees,id'],
            'status' => ['nullable', Rule::in([0, 1])],
        ]);

        $schedules = WorkFromHomeSchedule::query()
            ->with(['employee.user:id,name', 'employee.department:id,name'])
            ->when(isset($filters['employee_id']), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $schedules->getCollection()->transform(function (WorkFromHomeSchedule $schedule): WorkFromHomeSchedule {
            $balance = $this->workFromHomeService->balanceForSchedule($schedule);
            $schedule->setAttribute('balance_summary', [
                'allocated' => (float) ($balance?->allocated ?? 0),
                'used' => (float) ($balance?->used ?? 0),
                'balance' => (float) ($balance?->balance ?? 0),
                'pending' => (float) ($balance?->pending ?? 0),
            ]);

            return $schedule;
        });

        return Inertia::render('wfh-schedules/index', [
            'schedules' => $schedules,
            'filters' => [
                'employee_id' => $filters['employee_id'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
        ]);
    }

    public function create(): Response
    {
        $employees = Employee::query()
            ->with(['user:id,name', 'department:id,name'])
            ->select('id', 'user_id', 'employee_code', 'emp_num', 'department_id')
            ->where('status', 1)
            ->orderBy('id')
            ->get();

        return Inertia::render('wfh-schedules/create', [
            'employees' => $employees,
        ]);
    }

    public function store(StoreWorkFromHomeScheduleRequest $request): RedirectResponse
    {
        $this->workFromHomeService->createSchedule(
            $request->validated(),
            (int) $request->user()->id,
        );

        return redirect()->route('wfh-schedules.index')
            ->with('success', 'WFH schedule created successfully.');
    }

    public function updateStatus(Request $request, WorkFromHomeSchedule $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $this->workFromHomeService->updateScheduleStatus($schedule, (int) $validated['status']);

        return back()->with('success', 'WFH schedule status updated successfully.');
    }
}
